<?php
require_once __DIR__ . "/vendor/autoload.php";

class VGCompleteGenerate {
    private $cli;
    private $sourceDir;
    private $console;
    private $consoles = ['genesis'];
    private $wixCSV;
    private $manifest = [];
    public function __construct()
    {
        $this->cli = new \League\CLImate\CLImate;
    }

    public function run() {
        try {
            $this->init();
            $this->importCSV();
            $this->scanSourceDir();
            $this->generateManifest();
        } catch (Exception $e) {
            $this->cli->red($e->getMessage());
        }
    }

    private function init()
    {
        $this->sourceDir = isset($_SERVER['argv'][1]) 
            ? $_SERVER['argv'][1]
            : null;
        $this->console = isset($_SERVER['argv'][2])
            ? $_SERVER['argv'][2]
            : null;

        $this->wixCSV = isset($_SERVER['argv'][3])
            ? $_SERVER['argv'][3]
            : null;

        if(!is_dir($this->sourceDir)) {
            throw new Exception('first argument is not a valid directory');
        }

        if(!in_array($this->console, ['genesis'])) {
            throw new Exception('second argument must be one of: ' . implode(', ', $this->consoles));
        }

        if($this->wixCSV && !file_exists($this->wixCSV)) {
            throw new Exception('third argument is not a valid filename, or not readable');
        }
    }

    private function generateManifest()
    {
        file_put_contents('manifest.json', json_encode($this->manifest));
    }

    private function importCSV() {
        $csv = \League\Csv\Reader::createFromPath($this->wixCSV, 'r');
        //get the first row, usually the CSV header
        $csv->setHeaderOffset(0);

        foreach($csv as $r) {
            $filename = self::slugify($r['SortingTitle']) . '.json';
            $title = $r['Name'];
            $firstChar = substr($title, 0, 1);

            $this->manifest[$filename] = $r['Name'];
            $data = $this->getJSON(
                $title,
                $this->console,
                'NTSC-U',
                '__media/'.$firstChar.'/'.$title.'/'.'NTSC-U',
                [

                    'Title' => $r['Name'],
                    'SortingTitle' => $r['SortingTitle'],
                    'Description' => $r['Description'],
                    'Console' => $this->console,
                    'Region' => $r['Region'],
                    'Publisher' => $r['Publisher'],
                    'Developer' => $r['Developer'],
                    'Genre' => $r['Genra'],
                    'ReleaseDate' => $r['ReleaseDate'],
                    'MaxPlayers' => $r['Maximum Players'],
                    'PlayModes' => $r['PlayModes'],
                    'MenuScreenshot' => $r['Menu Screenshot'],
                    'ManualThumb' => $r['Manual Screenshot'],
                    'Manual' => $r['Manual'],
                    'GameplayScreenshot' => $r['Gameplay Screenshot'],
                    'FrontBoxart' => $r['BoxArtFront'],
                    'Cart' => $r['Cartridge'],
                    'BackBoxart' => $r['BoxArtFront'],
                    'YouTubeVideo' => $r["VideoURL"]
                ]
            );

            if($size = file_put_contents($this->console . '/' . $filename, $data)) {
                $this->cli->green(sprintf("csv: wrote %d bytes to %s", $size, $filename));
            }


            
        }
    }

    private function scanSourceDir($originalJSONBody = false) {
        $indexedFolders = scandir($this->sourceDir);

        foreach ($indexedFolders as $i) {
            $indexDir = $this->sourceDir.'/'.$i;
            if(is_dir($indexDir) && substr($i, 0, 1) != '.') {
                
                $titleFolders = scandir($indexDir);
                foreach ($titleFolders as $title) {

                    $titleFolder = $indexDir . '/' . $title;
                    if(is_dir($titleFolder) && substr($i, 0, 1) != '.') {

                        $ntscUFolder = $titleFolder . '/' . 'NTSC-U';
                        if(is_dir($ntscUFolder) && substr($i, 0, 1) != '.') {
                            $firstChar = substr($i, 0, 1);
                            if(ctype_digit($firstChar)) {
                                $firstChar = '0~9';
                            }

                            $filename = self::slugify($title) . '.json';
                            
                            $originalJSONBody = file_get_contents($this->console . '/' . $filename);
                            $originalJSON = json_decode($originalJSONBody, true);
                            $data = $this->getJSON(
                                $title,
                                $this->console,
                                'NTSC-U',
                                '__media/'.$firstChar.'/'.$title.'/'.'NTSC-U',
                                $originalJSON
                            );

                            if($size = file_put_contents($this->console . '/' . $filename, $data)) {
                                $this->cli->lightGreen(sprintf("media: wrote %d bytes to %s", $size, $filename));
                            }

                        }
                    }
                }
            }
        }
    }

    private function getJSON($title, $console, $region, $sourceFolder, $originalJSON = []) {
        return json_encode([
            'Title' => $title,
            'SortingTitle' => $originalJSON['SortingTitle'] ?? '',
            'Description' => $originalJSON['Description'] ?? '',
            'Console' => $console,
            'Region' => $region,
            'Publisher' => $originalJSON['Publisher'] ?? '',
            'Developer' => $originalJSON['Developer'] ?? '',
            'Genre' => $originalJSON['Genre'] ?? '',
            'ReleaseDate' => $originalJSON['ReleaseDate'] ?? '',
            'MaxPlayers' => $originalJSON['MaxPlayers'] ?? '',
            'PlayModes' => $originalJSON['PlayModes'] ?? '',
            'MenuScreenshot' => $this->getCandidateFile('MenuScreenshot', $sourceFolder),
            'ManualThumb' => $this->getCandidateFile('ManualThumb', $sourceFolder),
            'Manual' => $this->getCandidateFile('Manual', $sourceFolder, ['pdf']),
            'GameplayScreenshot' => $this->getCandidateFile('GameplayScreenshot', $sourceFolder),
            'FrontBoxart' => $this->getCandidateFile('FrontBoxart', $sourceFolder),
            'Cart' => $this->getCandidateFile('Cart', $sourceFolder),
            'BackBoxart' => $this->getCandidateFile('BackBoxart', $sourceFolder),
            'YouTubeVideo' => $originalJSON['YouTubeVideo'] ?? '',
        ], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
    }

    public static function slugify($text, string $divider = '-')
    {
        // replace non letter or digits by divider
        $text = preg_replace('~[^\pL\d]+~u', $divider, $text);

        // transliterate
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);

        // remove unwanted characters
        $text = preg_replace('~[^-\w]+~', '', $text);

        // trim
        $text = trim($text, $divider);

        // remove duplicate divider
        $text = preg_replace('~-+~', $divider, $text);

        // lowercase
        $text = strtolower($text);

        if (empty($text)) {
        return 'n-a';
        }

        return $text;
    }

    private function getCandidateFile($KeyFilename, $sourceFolder, $allowedExtensions = ['jpg', 'png', 'gif'])
    {
        foreach($allowedExtensions as $ext) {
            $testFilename = $KeyFilename . '.' . $ext;
            if(file_exists(getcwd() . '/' . $sourceFolder. '/'. $testFilename)) {
                return $sourceFolder . '/' . $testFilename;
            }

            $testFilename = $KeyFilename . '.' . strtoupper($ext);
            if(file_exists(getcwd() . '/' . $sourceFolder. '/'. $testFilename)) {
                return $sourceFolder . '/' . $testFilename;
            }
        }

        return '';
    }


}

(new VGCompleteGenerate())->run();