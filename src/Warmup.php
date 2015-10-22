<?php

namespace Kassner\WebsiteWarmup;

class Warmup
{

    private $root;
    private $context;
    private $db;

    public function __construct($root, \PDO $db)
    {
        $this->root = $root;
        $this->db = $db;
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

        try {
            $t1 = microtime(true);
            $content = file_get_contents($url, null, $this->getContext());
            echo sprintf('%.04f', microtime(true) - $t1) . ' ' . $this->printSize(strlen($content)) . ' ';

            if ($content === false || empty($http_response_header)) {
                throw new Exception('Cannot retrieve URL');
            }

            if ($this->isResponseGzipped($http_response_header) && $this->isGzipEnabled()) {
                // $content = gzinflate($content);
                // $content = gzinflate(substr($content,2,-4));
                $content = gzinflate(substr($content, 10));
            }

            if (preg_match_all("#http://{$this->root}([a-zA-Z0-9_.\-/?=%]*)#", $content, $matches)) {
                $this->addUrl($matches[0]);
            }

            $this->setOk($url);
            echo PHP_EOL;
        } catch (\Exception $e) {
            echo "Exception: {$e->getMessage()}", PHP_EOL;
            // var_dump($e->getTrace());
            $this->addUrl($url, 3);
        }
    }

    protected function setOk($url)
    {
        $this->addUrl($url, 2);
    }

    public function addUrl($url, $ok = 0)
    {
        $this->db->query("BEGIN EXCLUSIVE TRANSACTION");
        $replaceStmt = $this->db->prepare("REPLACE INTO urls (url, status) VALUES (:url, :status)");
        $existsStmt = $this->db->prepare("SELECT COUNT(1) AS total FROM urls where url = :url");

        if (!is_array($url)) {
            $url = [$url];
        }

        foreach ($url as $itemUrl) {
            if (!$existsStmt->execute(['url' => $itemUrl])) {
                echo "Error while updating status", __METHOD__, PHP_EOL;
                die;
            }

            $count = (int) $existsStmt->fetchColumn(0);

            if ($count > 0) {
                continue;
            }

            if (!$replaceStmt->execute([
                'url' => $itemUrl,
                'status' => $ok,
            ])) {
                echo "Error while updating status", __METHOD__, PHP_EOL;
                die;
            }
        }

        $this->db->query("COMMIT");
    }

    protected function getUrl()
    {
        $this->db->query("BEGIN EXCLUSIVE TRANSACTION");
        $selectStmt = $this->db->prepare("SELECT * FROM urls WHERE status = 0 ORDER BY id DESC LIMIT 1");

        if (!$selectStmt->execute()) {
            echo "Error while retrieving URL", PHP_EOL;
            die;
        }

        $item = $selectStmt->fetch(\PDO::FETCH_OBJ);

        $updateStmt = $this->db->prepare("UPDATE urls SET status = :status WHERE id = :id");

        if (!$updateStmt->execute([
            'status' => 1,
            'id' => $item->id,
        ])) {
            var_dump($this->db->errorInfo());
            echo "Error while updating status", __METHOD__, PHP_EOL;
            die;
        }

        $this->db->query("COMMIT");

        return $item->url;
    }

    protected function isGzipEnabled()
    {
        return true;
    }

    protected function getContext()
    {
        if (!$this->isGzipEnabled()) {
            return null;
        }

        if (empty($this->context)) {
            $this->context = stream_context_create([
                'http' => [
                    'timeout' => 20,
                    'header' => 'Accept-Encoding: gzip',
                    'follow_location' => false,
                ]
            ]);
        }

        return $this->context;
    }

    protected function isResponseGzipped($headers)
    {
        foreach ($headers as $h) {
            if (stristr($h, 'content-encoding') and stristr($h, 'gzip')) {
                return true;
            }
        }

        return false;
    }

    protected function printSize($bytes)
    {
        return sprintf('%.02f', $bytes / 1024) . ' KB';
    }

}
