<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TextAnalyzeController extends Controller
{
    /*
     * @property $density must be adjusted according to length of context, in my cases 3 is okay
     */
    private $density = 3;

    private $min_word_length = 4;
    private $context = null;
    private $keywords = [];

    private $_killed = [];

    public function density() {
        $_context = Storage::disk('local')->get("public/bigbang.txt");

        $bad_chars = [0,1,2,3,4,5,6,7,8,9,"(",")","[","]","{","}","?","!",".",",","#","@", "?", "  "];
        $_prepare = utf8_decode(strtolower(str_replace($bad_chars, null, $_context)));
        $this->context = explode(" ", $_prepare);
        $_lentgh = count($this->context);

        $_rate = (int)($_lentgh/$this->density);

        $_point = 1;
        for ($_i = 0; $_i<$this->density; $_i++) {
            $this->walker($_rate, $_point);
            $_rate = $_rate/$this->density;
            $_point = $_point*10;

            //you can remove this line at below if you want to measure keywords density in the remaining context
            $this->_killed = [];
        }
        arsort($this->keywords);

        Storage::disk('local')->put('public/keywords.txt', json_encode($this->keywords));

        $_keywords = $this->keywords;

        $most_dense_keywords = [];
        $dense_keywords = [];
        $less_dense_keywords = [];
        foreach ($_keywords as $index => $weight) {
            if ($weight > 1000) {
                $most_dense_keywords[] = ["keyword" => $index, "weight" => $weight];
                unset($_keywords[$index]);
            }elseif ($weight > 1) {
                $dense_keywords[] = ["keyword" => $index, "weight" => $weight];
                unset($_keywords[$index]);
            }else{
                $less_dense_keywords[] = ["keyword" => $index, "weight" => $weight];
                $_keywords[$index] = " " . $_keywords[$index] . " ";
            }
        }

        Storage::disk('local')->put('public/most_dense_keywords.txt', json_encode($most_dense_keywords));
        Storage::disk('local')->put('public/dense_keywords.txt', json_encode($dense_keywords));
        Storage::disk('local')->put('public/less_dense_keywords.txt', json_encode($less_dense_keywords));

        $_target_words = array_keys($_keywords);

        $_trim_result = str_replace($_target_words, null, " " . $_prepare . " ");

        Storage::disk('local')->put('public/trim_result.txt', $_trim_result);
    }

    private function _rander() {
        $_rand = rand(0, (count($this->context)-1));
        if (in_array($_rand, $this->_killed) || strlen($this->context[$_rand]) < $this->min_word_length) {
            $_rand = $this->_rander();
        }
        return $_rand;
    }

    private function walker(int $rate, int $point)  {
        for ($_i = 0; $_i<$rate; $_i++) {
            $_rand = $this->_rander();
            $word = $this->context[$_rand];
            if (!key_exists($word, $this->keywords)) {
                if ($point == 1) {
                    $this->keywords[$word] = 1;
                }
            }else{
                $this->keywords[$word] = $this->keywords[$word]*$point;
            }
        }
    }
}
