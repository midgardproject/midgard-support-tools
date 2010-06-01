<?php

pake_desc('Grab latest snapshot of projects from github, produce packages and deploy to OBS');
pake_task('obs_all', 'pack_all');

pake_desc('Grab snapshot of projects (latest, or versioned) from github and produce packages. usage: pake pack_all [10.05.1]');
pake_task('pack_all', 'clean');

pake_desc('Grab latest snapshot of project from github and produce package. usage: pake pack packagename');
pake_task('pack', 'clean');

pake_desc('Remove temporary files');
pake_task('clean');

pake_task('default');



function run_default()
{
    pakeApp::get_instance()->display_tasks_and_comments();
}

function run_clean()
{
    $target = dirname(__FILE__).'/target';
    pake_remove_dir($target);
}

function run_obs_all()
{
    pake_echo_comment("Uploading to OBS…");

    $cwd = getcwd();
    chdir($root);

    $php_exec = (isset($_SERVER['_']) and substr($_SERVER['_'], -4) != 'pake') ? $_SERVER['_'] : pake_which('php');
    pake_sh(escapeshellarg($php_exec).' obs_upload.php', true);

    chdir($cwd);
}

function run_pack_all($task, $args)
{
    $root = dirname(__FILE__);

    $options = pakeYaml::loadFile($root.'/options.yaml');

    $packages = $options['packages'];

    if (isset($args[0]))
    {
        $version = $args[0];
        $options['branch'] = $version; // override, to force download from tag
    }
    else
    {
        $version = $options['version'];
    }

    $cwd = getcwd();
    foreach ($packages as $package)
    {
        pake_echo_comment($package);
        create_package($root.'/target', $package, $version, $options);
    }

    create_midgardmvc_package($root.'/target', $version, $options);

    pake_echo_comment('Creating "AllinOne" archive');
    create_allinone($root.'/target', $version);

    chdir($cwd);
}

function run_pack($task, $args)
{
    if (!isset($args[0]))
        throw new pakeException('usage: pake pack packagename');

    $package = $args[0];

    $root = dirname(__FILE__);
    $options = pakeYaml::loadFile($root.'/options.yaml');

    $cwd = getcwd();

    pake_mkdirs($root.'/target');
    pake_mkdirs($root.'/target/obs');

    create_package($root.'/target', $package, $options['version'], $options);

    chdir($cwd);
}



function create_allinone($target, $version)
{
    $name = 'Midgard_AllinOne-'.$version;
    $dir = $target.'/'.$name;

    pake_mkdirs($dir);

    foreach (pakeFinder::type('file')->name('*.tar.gz')->in($target) as $file)
    {
        pake_sh('tar xf '.escapeshellarg($file).' -C '.escapeshellarg($dir));
    }

    chdir($target);
    pake_sh('tar czf '.escapeshellarg($dir.'.tar.gz').' '.escapeshellarg($name));
    pake_remove_dir($dir);
}

function create_package($target, $package, $version, $options)
{
    pake_echo_comment('--> Downloading');
    _download_package($target, $package, $options['branch']);

    pake_echo_comment('--> Extracting');
    _extract_package($target, $package);

    pake_echo_comment('--> Processing');
    $func = '_process_'.str_replace('-', '_', $package);

    if (!function_exists($func))
    {
        throw new pakeException('Can not find function for processing "'.$package.'"');
    }

    call_user_func($func, $target, $version, $options);
}

function create_midgardmvc_package($target, $version, $options)
{
    $name = 'midgardmvc_core-'.$version;
    $mvc_dir = $target.'/'.$name;

    pake_mkdirs($mvc_dir);

    pake_echo_comment('Getting mvc-components');
    foreach ($options['mvc_packages'] as $package)
    {
        _download_package($target, $package, $options['branch']);
        _extract_package($target, $package);

        pake_sh('mv '.escapeshellarg($target.'/'.$package).' '.escapeshellarg($mvc_dir.'/'.$package));
    }

    pake_echo_comment('Creating runtime-bundle');
    _create_runtime_bundle($target, 'simple-bundle', $mvc_dir);

    pake_echo_comment('Adding dependencies');
    foreach ($options['mvc_dependencies'] as $file)
    {
        pake_copy('http://pear.indeyets.pp.ru/get/'.$file, $mvc_dir.'/'.$file);
    }

    chdir($target);
    pake_sh('tar czf '.escapeshellarg($mvc_dir.'.tar.gz').' '.escapeshellarg($name));
    pake_remove_dir($mvc_dir);
}



function _create_runtime_bundle($target, $name, $mvc_dir)
{
    pake_remove_dir($target.'/'.$name);
    pake_remove(pakeFinder::type('any')->name($name.'.zip')->maxdepth(0), $target);

    pake_mkdirs($target.'/'.$name);
    pake_mkdirs($target.'/tmp');

    // 1. get PHPTAL tarball and write it as PHPTAL folder and PHPTAL.php
    pake_copy('http://phptal.org/files/PHPTAL-1.2.1.zip', $target.'/tmp/phptal.zip');
    pakeArchive::extractArchive($target.'/tmp/phptal.zip', $target.'/tmp', true);

    pake_mkdirs($target.'/'.$name.'/PHPTAL');
    pake_mirror(pakeFinder::type('any')->ignore_version_control(), $target.'/tmp/PHPTAL-1.2.1/PHPTAL', $target.'/'.$name.'/PHPTAL');
    pake_mirror('PHPTAL.php', $target.'/tmp/PHPTAL-1.2.1', $target.'/'.$name);

    pake_remove_dir($target.'/tmp');

    // 2. Copy components inside
    pake_sh('cp -R '.escapeshellarg($mvc_dir.'/').'* '.escapeshellarg($target.'/'.$name.'/'));

    // 3. Create manifest
    $data = array('type' => 'runtime bundle', 'name' => $name);
    pakeYaml::emitFile($data, $target.'/'.$name.'/manifest.yml');

    // 4. pack archive
    $finder = pakeFinder::type('any')->ignore_version_control();
    pakeArchive::createArchive($finder, $target.'/'.$name, $mvc_dir.'/'.$name.'.zip');

    pake_remove_dir($target.'/'.$name);
}

function _download_package($target, $package, $branch)
{
    pake_mkdirs($target);
    pake_copy('http://github.com/midgardproject/'.$package.'/tarball/'.$branch,
              $target.'/'.$package.'.tar.gz',
              array('override' => true)
    );
}

function _extract_package($target, $package)
{
    $filename = $target.'/'.$package.'.tar.gz';
    pake_mkdirs($target.'/tmp');

    pake_sh('tar xf '.escapeshellarg($filename).' -C '.escapeshellarg($target.'/tmp'));
    pake_sh('mv '.escapeshellarg($target.'/tmp/midgardproject-'.$package).'* '.$target.'/'.$package);
    pake_remove_dir($target.'/tmp');

    pake_remove($filename, '');
}

function _process_midgard_core($target, $version, $options)
{
    preg_replace_in_file(
        '/('.preg_quote('AC_INIT([midgard3-core],[', '/').').*('.preg_quote('])', '/').')/',
        '${1}'.$version.'\2',
        $target.'/midgard-core/configure.in'
    );

    chdir($target.'/midgard-core');
    $log = pake_sh($target.'/midgard-core/autogen.sh --prefix='.$options['core_prefix']);

    if (!file_exists($target.'/midgard-core/Makefile'))
    {
        throw new pakeException("failed to configure midgard-core: \n\n".$log);
    }

    pake_sh('make dist');
    $filename = 'midgard3-core-'.$version.'.tar.gz';
    pake_rename($target.'/midgard-core/'.$filename, $target.'/'.$filename);

    chdir($target);
    pake_remove_dir($target.'/midgard-core');
}

function _process_midgard_python($target, $version)
{
    preg_replace_in_file(
        '/('.preg_quote("version = '", '/').').*('.preg_quote("',", '/').')/',
        '${1}'.$version.'\2',
        $target.'/midgard-python/setup.py'
    );

    chdir($target.'/midgard-python');
    pake_sh('python setup.py sdist --formats gztar --dist-dir '.escapeshellarg($target).' --keep-temp');

    chdir($target);
    pake_remove_dir($target.'/midgard-python');
}

function _process_midgard_php5($target, $version)
{
    chdir($target);

    $name = 'php5-midgard3-'.$version;
    $new_dirname = $target.'/'.$name;

    pake_sh('mv '.escapeshellarg($target.'/midgard-php5').' '.escapeshellarg($new_dirname));
    pake_sh('tar czf '.escapeshellarg($new_dirname.'.tar.gz').' '.escapeshellarg($name));
    pake_remove_dir($new_dirname);
}

function _process_midgard_runtime($target, $version)
{
    chdir($target);

    $name = 'midgard3-runtime-'.$version;
    $new_dirname = $target.'/'.$name;

    pake_sh('mv '.escapeshellarg($target.'/midgard-runtime/qt').' '.escapeshellarg($new_dirname));
    pake_remove_dir($target.'/midgard-runtime');

    pake_sh('tar czf '.escapeshellarg($new_dirname.'.tar.gz').' '.escapeshellarg($name));
    pake_remove_dir($new_dirname);
}



// helpers follow this line

function preg_replace_in_file($pattern, $replacement, $filename)
{
    $subject = file_get_contents($filename);
    $result = preg_replace($pattern, $replacement, $subject);
    file_put_contents($filename, $result);

    pake_echo_action('preg_replace', $filename);
}
