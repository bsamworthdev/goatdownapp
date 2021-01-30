<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Word;
use Illuminate\Support\Facades\Log;

class WordController extends Controller
{
    //
    public function fetch(Request $request){
        ini_set('max_execution_time', 1800);
        $action = $request->action;
        if ($action == 'getConundrums'){
            //$this->populateLetters();
            return $this->getConundrums();
        }
    }

    //Split words into their component letters
    public function update(Request $request){
        $action = $request->action;
        if ($action == 'populateLetters'){
            $this->populateLetters();
        }
    }

    public function getConundrums(){
        $words = $this->getAllNineLetterWords();
        // $words = $this->removeWordsWithAnagrams($words);
        $words = $this->addWildCardedWords($words);
        $words = $this->removeDuplicatedWildcardWords($words);
        
        //Sort alphabeticlly
        // $words = collect($words);
        // $sorted_words = $words->sortBy('word', true);
        // $words = $words->sortBy(function ($item) {
        //     return $item->word;
        // });
        // $sorted_words = $sorted_words->all();

// Log::Debug($sorted_words);

        return json_encode($words);
    }

    public function removeDuplicatedWildcardWords($words){
        //Create master list
        $wildcarded_letters_master = [];
        foreach ($words as $word){
            $wildcarded_letters_this_word = [];
            foreach ($word->wildcarded_letters as $wildcarded_letters){
                if (!in_array($wildcarded_letters, $wildcarded_letters_this_word)){
                    $wildcarded_letters_this_word[] = $wildcarded_letters;
                }
            }
            $wildcarded_letters_master = array_merge($wildcarded_letters_master, $wildcarded_letters_this_word);
        }

        // Log::Debug($wildcarded_letters_master);

        //Sort master list alphabetically
        sort($wildcarded_letters_master);

        //Find duplicates in masterlist
        $prev='';
        $duplicate_letters = [];
        foreach ($wildcarded_letters_master as $wildcarded_letters){
            if ($wildcarded_letters==$prev){
                $duplicate_letters[] = $wildcarded_letters;
            }
            $prev = $wildcarded_letters;
        }

        foreach ($words as $word){
            $filtered = [];
            foreach ($word->wildcarded_words as $wildcarded_words){
                $wildcarded_letters = $this->breakWordIntoLetters($wildcarded_words);
                if (!in_array($wildcarded_letters ,$duplicate_letters)){
                    $filtered[] = $wildcarded_words;
                } 
            }
            $word->wildcarded_words = $filtered;
        }

        return $words;

    }

    public function addWildCardedWords($words){
        //Add a wildcard in each string position in turn
        foreach ($words as $word){
            $wildcarded_words = [];
            $wildcarded_letters = [];
             for($i=0; $i<9; $i++)
             {
                 $wildcarded_word = substr_replace(strtolower($word->word),'?',$i,1);
                 $wildcarded_words[] = $wildcarded_word;
                 $wildcarded_letters[] = $this->breakWordIntoLetters($wildcarded_word);
             }
             $word->wildcarded_words = $wildcarded_words;
             $word->wildcarded_letters = $wildcarded_letters;
         }
         return $words;
    }

    public function removeWordsWithAnagrams($words){
        //Get words with no anagrams
        $new_words=[];
        $prevLetters='';
        foreach ($words as $word){
            if ($word->letters!=$prevLetters){
                $new_words[] = $word;
            }
            $prevLetters=$word->letters;
        }
        $new_words = collect($new_words);

        return $new_words;
    }

    public function getAllNineLetterWords(){
        return Word::whereRaw('LENGTH(word) = 9')
        ->where('letters', 'not like', "%'%")
        ->where('letters', 'not like', "%.%")
        ->where('letters', 'not like', "%-%")
        ->where('letters', 'not like', "% %")
        ->where('letters', 'not like', "%/%")
        ->distinct('letters')
        ->orderBy('word')
        ->get(['word','letters']);
    }

    public function populateLetters(){
        $words = Word::whereNull('letters')
        ->get(['id', 'word']);
        foreach($words as $word){
            $word->letters = $this->breakWordIntoLetters(strtolower($word->word));
            $word->save();
        }
    }

    public function breakWordIntoLetters($word){
        $letters = str_split($word);
        sort($letters);

        return implode("",$letters);
    }
}
