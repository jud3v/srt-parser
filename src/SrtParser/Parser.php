<?php

namespace Benlipp\SrtParser;

use Benlipp\SrtParser\Exceptions\FileNotFoundException;
use ErrorException;
use Stichoza\GoogleTranslate\GoogleTranslate;


class Parser
{
    private $data;
    const SRT_REGEX_STRING = '/\d\r\n\r\n((?:.*\r\n)*)\r\n/';

    /**
     * @param $file
     * @return $this
     * @throws FileNotFoundException
     */
    public function loadFile($file)
    {
        try {
            $fileContents = file_get_contents($file);
        } catch (\Exception $e) {
            throw new FileNotFoundException($file);
        }
        $this->data = $fileContents;

        return $this;
    }

    /**
     * @param $string
     * @return $this
     */
    public function loadString($string)
    {
        $this->data = $string;

        return $this;
    }

    /**
     * @param null $sourceLanguage
     * @param null $targetLanguage
     * @return array
     * @throws ErrorException
     */
    public function parse($sourceLanguage = null, $targetLanguage = null)
    {
        $splitData = self::splitData($this->data);
        $data = ['source' => $sourceLanguage, 'target' => $targetLanguage];
        return !is_null($data['source']) && !is_null($data['target']) ? self::buildCaptions($splitData, $data) : self::buildCaptions($splitData);
    }

    /**
     * split data into workable chunks
     * @param $data
     * @return array
     */
    private static function splitData($data)
    {
        //find digits followed by a single line break and timestamps
        $sections = preg_split('/\d+(?:\r\n|\r|\n)(?=(?:\d\d:\d\d:\d\d,\d\d\d)\s-->\s(?:\d\d:\d\d:\d\d,\d\d\d))/m', $data,-1,PREG_SPLIT_NO_EMPTY);
        $matches = [];
        foreach ($sections as $section) {
            //cleans out control characters, borrowed from https://stackoverflow.com/a/23066553
            $section = preg_replace('/[^\PC\s]/u', '', $section);
            if(trim($section) == '') continue;
            $matches[] = preg_split('/(\r\n|\r|\n)/', $section, 2,PREG_SPLIT_NO_EMPTY);
        }
        return $matches;
    }

    /**
     * @param $matches
     * @param null $data
     * @return array
     * @throws ErrorException
     */
    private static function buildCaptions($matches,$data = null)
    {
        $captions = [];
        foreach ($matches as $match) {
            $times = self::timeMatch($match[0]);
            $text = self::textMatch($match[1]);
            if (!is_null($data) && !is_null($data['source']) && !is_null($data['target'])){
                $tr = new GoogleTranslate();
                $captions[] = new Caption($times['start_time'], $times['end_time'],$tr->setSource($data['source'])->setTarget($data['target'])->translate($text));
            } else {
                $captions[] = new Caption($times['start_time'], $times['end_time'], $text);
            }
        }

        return $captions;
    }

    /**
     * @param $timeString
     * @return array
     */
    private static function timeMatch($timeString)
    {
        $matches = [];
        preg_match_all('/(\d\d:\d\d:\d\d,\d\d\d)\s-->\s(\d\d:\d\d:\d\d,\d\d\d)/', $timeString, $matches,
            PREG_SET_ORDER);
        $time = $matches[0];

        return [
            'start_time' => $time[1],
            'end_time'   => $time[2]
        ];
    }

    /**
     * @param $textString
     * @return string|string[]
     */
    private static function textMatch($textString)
    {
        $text = rtrim($textString);
        $text = str_replace("\r\n", "\n", $text);

        return $text;
    }
}
