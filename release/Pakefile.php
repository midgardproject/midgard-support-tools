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

    pake_mkdirs($root.'/target');
    pake_remove(pakeFinder::type('any'), $root.'/target');

    pake_echo_comment('Downloading snapshots');
    foreach ($packages as $package)
    {
        pake_copy('http://github.com/midgardproject/'.$package.'/tarball/master',
                  $root.'/target/'.$package.'.tar.gz',
                  array('override' => true)
        );
    }

    pake_echo_comment('Extracting');
    foreach ($packages as $package)
    {
        $filename = $root.'/target/'.$package.'.tar.gz';
        pake_mkdirs($root.'/target/tmp');

        pake_sh('tar xf '.escapeshellarg($filename).' -C '.escapeshellarg($root.'/target/tmp'));
        pake_sh('mv '.escapeshellarg($root.'/target/tmp/midgardproject-'.$package).'* '.$root.'/target/'.$package);
        pake_remove_dir($root.'/target/tmp');
    }

    pake_remove(pakeFinder::type('file')->maxdepth(0), $root.'/target');

    pake_echo_comment('Preparing for packaging');
    throw new pakeException('not finished');
}
