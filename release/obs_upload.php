<?php

if (!function_exists("curl_exec"))
    die("Curl extension not loaded");

define("OBS_PROJECT",     "home:midgardproject:ratatoskr");
define("OBS_BASE_URL",    "https://api.opensuse.org/source/". rawurlencode (OBS_PROJECT) . "/");
define("OBS_USER_FILE",   "~/.midgard/obs_user");

define("CORE_PACKAGE",    "midgard2-core");
define("PYTHON_PACKAGE",  "python-midgard2");
define("PHP_PACKAGE",     "php5-midgard2");
define("MVC_PACKAGE",     "midgard2-mvc");
define("RUNTIME_PACKAGE", "midgard2-runtime");

define("CORE_SVN_DIR",    "midgard-core");
define("PHP_SVN_DIR",     "midgard-php5");
define("PYTHON_SVN_DIR",  "midgard-python");
define("RUNTIME_SVN_DIR", "midgard-runtime");
define("MVC_SVN_DIR",     "midgardmvc");

/* get username and password */
$data_file = shell_exec ("cat " . OBS_USER_FILE);

if ($data_file == false)
{
    echo "Can not read ".OBS_USER_FILE." file \n";
    exit(1); // errcode
}

$data = explode("\n", $data_file);

$mmc = new midgard_makedist_curl($data);
$mmc->debug = 1;
$mmc->execute(getcwd());

class midgard_makedist_curl
{
    var $username = null;
    var $password = null;
    var $curl = null;
    var $cookie_file = "/tmp/obs_cookie";
    var $debug = 0;

    function __construct(array $data)
    {
        if ($data[0] == ""
            || $data[0] == null)
        {
            die("Can not initialize curl with empty username");
        }

        if ($data[0] == ""
            || $data[0] == null)
        {
            die("Can not initialize curl with empty username");
        }

        $this->username = $data[0];
        $this->password = $data[1];
    }

    private function debug($msg = "I have nothing to say", $level = 1)
    {
        if ($this->debug >= $level)
            echo $msg . "\n";
    }

    private function _curl_init()
    {
        if ($this->curl != null)
            throw new Exception ("Curl already being initialized");

        $this->curl = curl_init();

        /* Set common curl options */
        curl_setopt ($this->curl, CURLOPT_FAILONERROR, 1);
        curl_setopt ($this->curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt ($this->curl, CURLOPT_RETURNTRANSFER,1);
        curl_setopt ($this->curl, CURLOPT_PORT, 443);
        curl_setopt ($this->curl, CURLOPT_TIMEOUT, 15);
        curl_setopt ($this->curl, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.6) Gecko/20070725 Firefox/2.0.0.6");
        curl_setopt ($this->curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt ($this->curl, CURLOPT_USERPWD, $this->username . ":" . $this->password);
    }

    private function _curl_close()
    {
        if ($this->curl == null)
            return;

        curl_close($this->curl);

        $this->curl = null;
    }

    private function build_dists_obs_path($dir)
    {
        return dirname(__FILE__).'/dists/'.$dir.'/OBS/';
    }

    private function list_dists_obs_files($dir)
    {
        $ret = array();

        $_obs_files_dir = self::build_dists_obs_path ($dir);
        $handle = opendir($_obs_files_dir);

        if (!$handle)
            return $ret;

        while (($filename = readdir ($handle)) !== false)
        {
            $filepath = $_obs_files_dir . "/" . $filename;

            if (!is_file ($filepath))
            {
                continue;
            }

            if ($filepath[0] == '.' && is_dir ($filepath))
            {
                continue;
            }

            $ret[] = $filepath;
        }

        closedir ($handle);

        return $ret;
    }

    private function remove_tarball($url, $filename)
    {
        /* TODO */
    }

    private function _set_put_tarball_options($package, $filename)
    {
        if ($this->curl == null)
            throw new Exception ("Curl not initialized");

        $url = OBS_BASE_URL . $package . "/" . basename ($filename);
        $size = filesize ($filename);
        $file = fopen ($filename,'r');

        curl_setopt ($this->curl, CURLOPT_URL, $url);
        curl_setopt ($this->curl, CURLOPT_PUT, true);
        curl_setopt ($this->curl, CURLOPT_INFILESIZE, $size);
        curl_setopt ($this->curl, CURLOPT_INFILE, $file);
    }

    private function _upload()
    {
        $ret = curl_exec ($this->curl);

        if (strstr ($ret, "<summary>Ok</summary>"))
        {
            echo " STATUS: OK \n";
        }
        else {

            echo " STATUS: FAILED ";
            echo $ret . "\n";
            echo curl_error ($this->curl) . "\n";
        }

        $this->_curl_close();
    }

    private function _upload_dists_obs_files($dir, $package)
    {
        $ret = self::list_dists_obs_files ($dir);

        if (empty ($ret))
            return;

        foreach ($ret as $filename)
        {
            $this->_curl_init();
            $this->_set_put_tarball_options ($package, $filename);
            echo "Uploading " . $filename;
            $this->_upload();
        }
    }

    private function upload_package_files($package, $tarball, $svndir)
    {
        if ($package === "" || $package == null)
        {
            echo "Can not upload files for empty package \n";
            return;
        }

        if ($tarball === "" || $tarball == null)
        {
            echo "Can not upload tarball. Empty name given \n";
            return;
        }

        if ($svndir === "" || $svndir == null)
        {
            echo "Can not upload files from svndir. Empty name given \n";
        }

        $this->_curl_init();
        $this->_set_put_tarball_options ($package, $tarball);
        echo "Uploading " . $tarball;
        $this->_upload();

        if ($svndir)
        {
            $this->_upload_dists_obs_files ($svndir, $package);
        }
    }

    function upload_core($filename)
    {
        $this->_curl_init();
        $this->_set_put_tarball_options (CORE_PACKAGE, $filename);
        echo "Uploading " . $filename;
        $this->_upload();
    }

    function upload_python($filename)
    {
        $this->_curl_init();
        $this->_set_put_tarball_options (PYTHON_PACKAGE, $filename);
        echo "Uploading " . $filename;
        $this->_upload();
    }

    function upload_php($filename)
    {
         $this->_curl_init();
        $this->_set_put_tarball_options (PHP_PACKAGE, $filename);
        echo "Uploading " . $filename;
        $this->_upload();
    }

    function upload_mvc($filename)
    {
         $this->_curl_init();
        $this->_set_put_tarball_options (MVC_PACKAGE, $filename);
        echo "Uploading " . $filename;
        $this->_upload();
    }

    function execute($dir)
    {
        chdir($dir);

        $core_file =    glob("target/midgard2-core*.tar.gz");
        $python_file =  glob("target/python-midgard2*.tar.gz");
        $php_file =     glob("target/php5-midgard2*.tar.gz");
        $runtime_file = glob("target/midgard2-runtime*.tar.gz");
        $mvc_file =     glob("target/midgardmvc_core*.tar.gz");

        if (count($core_file) > 1)
            throw new Exception("More than one core tarball found");

        if (count($python_file) > 1)
            throw new Exception("More than one python tarball found");

        if (count($php_file) > 1)
            throw new Exception("More than one php tarball found");

        $this->upload_package_files(CORE_PACKAGE,    $core_file[0],    CORE_SVN_DIR);
        $this->upload_package_files(PHP_PACKAGE,     $php_file[0],     PHP_SVN_DIR);
        $this->upload_package_files(PYTHON_PACKAGE,  $python_file[0],  PYTHON_SVN_DIR);
        $this->upload_package_files(RUNTIME_PACKAGE, $runtime_file[0], RUNTIME_SVN_DIR);
        $this->upload_package_files(MVC_PACKAGE,     $mvc_file[0],     MVC_SVN_DIR);
    }
}
