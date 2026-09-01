<?php

namespace App\Http\Controllers;

use App\Models\LocalizedText;
use Illuminate\Http\Request;

class LocalizedTextController extends Controller
{
    public function store(Request $request)
    {
        $text = LocalizedText::create($request->all());

        return $text;
    }
    public function update(Request $request)
    {
        $key = $request->key;
        $new_text = $request->text;
        $local = $request->locale ?? 'fa';
        $text = LocalizedText::where('key',$key)->where('locale',$local)->first();

        $text->text = $new_text;
        $text->save();
        return $text;

    }
    public function get_text_by_key($key,$locale='fa')
    {
        $text = LocalizedText::where('key',$key)
        ->where('locale',$locale)
        ->first();
        return $text->text;
    }


}
