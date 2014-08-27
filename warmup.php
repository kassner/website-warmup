<?php

/**
 * @TODO create some abstraction to file handling
 */
class Warmup
{

    private $root;
    private $cacheFile;

    public function __construct($root, $cacheFile = 'warmup.json')
    {
        /**
         * @TODO sanitize $root
         * @TODO $cacheFile
         */
        $this->root = $root;
        $this->cacheFile = $cacheFile;

        if (!file_exists($this->cacheFile)) {
            file_put_contents($this->cacheFile, '');
            $this->addUrl("http://{$this->root}/");
        }
    }

    public function run()
    {
        do {
            $url = $this->getUrl();

            if (empty($url)) {
                break;
            }

            $this->processUrl($url);
        } while (true);
    }

    protected function processUrl($url)
    {
        echo "Fetching {$url} ";
        $t1 = microtime(true);
        $content = file_get_contents($url);
        echo sprintf('%.04f', microtime(true) - $t1), PHP_EOL;
        $this->setOk($url);

        if (preg_match_all("#http://{$this->root}([a-zA-Z0-9_.\-/?=%]*)#", $content, $matches)) {
            $this->addUrl($matches[0]);
        }
    }

    protected function setOk($url)
    {
        $this->addUrl($url, 2);
    }

    protected function addUrl($url, $ok = 0)
    {
        $f = fopen($this->cacheFile, 'r+');
        flock($f, LOCK_EX);

        $data = json_decode(fgets($f), true);

        if (is_null($data)) {
            $data = array();
        }

        if (is_array($url)) {
            foreach ($url as $item) {
                if (!empty($data[$item])) {
                    continue;
                }

                $data[$item] = $ok;
            }
        } else {
            $data[$url] = $ok;
        }

        rewind($f);
        fwrite($f, json_encode($data));
        fflush($f);
        flock($f, LOCK_UN);
        fclose($f);
    }

    protected function getUrl()
    {
        $f = fopen($this->cacheFile, 'r+');
        flock($f, LOCK_EX);

        $data = json_decode(fgets($f), true);

        if (is_null($data)) {
            $data = array();
        }

        $url = array_search(0, $data);
        $data[$url] = 1;

        rewind($f);
        fwrite($f, json_encode($data));
        fflush($f);
        flock($f, LOCK_UN);
        fclose($f);

        return $url;
    }

}

if (empty($_SERVER['argv'][1])) {
    echo "Usage: php warmup.php example.com/some-root", PHP_EOL;
    die;
}

$warmup = new Warmup($_SERVER['argv'][1]);
$warmup->run();
