<?php

pake_desc('Grab latest snapshot of projects from github, produce packages and deplot to OBS');
pake_task('nightly');

pake_task('default');
function run_default()
{
    pakeApp::get_instance()->display_tasks_and_comments();
}

function run_nightly()
{
    $root = dirname(__FILE__);

    $packages = pakeYaml::loadFile($root.'/packages.yaml');
    $options = pakeYaml::loadFile($root.'/options.yaml');

    pake_mkdirs($root.'/target');
    pake_remove(pakeFinder::type('any'), $root.'/target');

    $cwd = getcwd();
    foreach ($packages as $package)
    {
        pake_echo_comment($package);

        pake_echo_comment('--> Downloading');
        pake_copy('http://github.com/midgardproject/'.$package.'/tarball/master',
                  $root.'/target/'.$package.'.tar.gz',
                  array('override' => true)
        );

        pake_echo_comment('--> Extracting');
        _extract_package($root.'/target', $package);

        pake_echo_comment('--> Processing');
        $func = '_process_'.str_replace('-', '_', $package);
        call_user_func($func, $root.'/target', $options['version'], $options);
    }

    // TODO: midgardmvc
    // TODO: midgard_runtime

    pake_echo_comment('Creating "AllinOne" archive');
    _create_allinone($root.'/target', $options['version']);

    chdir($cwd);
}

function _create_allinone($target, $version)
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
        '/('.preg_quote('AC_INIT([midgard2-core],[', '/').').*('.preg_quote('])', '/').')/',
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
    $filename = 'midgard2-core-'.$version.'.tar.gz';
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
    $new_dirname = $target.'/php5-midgard2-'.$version;

    pake_sh('mv '.escapeshellarg($target.'/midgard-php5').' '.escapeshellarg($new_dirname));
    pake_sh('tar czf '.escapeshellarg($new_dirname.'.tar.gz').' '.escapeshellarg('php5-midgard2-'.$version));
    pake_remove_dir($new_dirname);
}

function preg_replace_in_file($pattern, $replacement, $filename)
{
    $subject = file_get_contents($filename);
    $result = preg_replace($pattern, $replacement, $subject);
    file_put_contents($filename, $result);

    pake_echo_action('preg_replace', $filename);
}
