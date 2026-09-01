<?php

namespace App\Http\Controllers;
use App\Models\TransactionImage;
use Storage;
use File;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Imagick\Driver;
use App\Services\TelegramService;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;


class TransactionImageController extends Controller
{

    public function getTransactionImage($trID)
    {
        try {
            $data = TransactionImage::where('transaction_id', $trID)->get();
            if ($data != null) {
                return $data;
            } else {
                return response()->json('No Image', 404);
            }
        } catch (\Throwable $th) {
            \Log::info("Throwable:  $th");

            return response()->json('Server Error', 500);
        }
    }
    public function addNewTransactionImage(Request $request)
    {
        try {
            $data = new TransactionImage();
            $data->transaction_id = $request->transaction_id;
            $data->img_src = $request->img_src;
            $data->account_id = $request->account_id;
            $data->user_text = $request->user_text;
            $data->save();
            return $data->id;
        } catch (\Throwable $th) {
            \Log::info("Throwable:  $th");

            return response()->json('Server Error', 500);
        }
    }
    public function saveNewTransactionImage(Request $request)
    {
        try {

            // download image from $request->image_url and save on disk


            $telegramService = new TelegramService();


            $img_src = $request->img_src;

            // Get the image contents
            $imageContents = $telegramService->downloadImageFile($img_src);

            // Define the image path and name
            $imagePath = 'images/';
            $imageName ='transaction_'.time() .  basename($img_src);

            // Save the image on disk
            // save image path in $path
            $path = $imagePath . $imageName;
            try {
                // check if the image path is writable
                if (is_writable($imagePath)) {
                    Storage::disk('public')->put($imagePath . $imageName, $imageContents);
                } else {
                    // add permission to the image path
                    chmod($imagePath, 0777);
                    Storage::disk('public')->put($imagePath . $imageName, $imageContents);
                }



            } catch (\Throwable $th) {
                \Log::info("saveNewTransactionImage:  $th");
            }

            $data = new TransactionImage();
            $data->transaction_id = $request->transaction_id;
            $data->img_src ='/storage/'. $path;

            $data->account_id = $request->account_id;
            $data->user_text = $request->user_text;
            $data->save();
            return $data->id;
        } catch (\Throwable $th) {
            \Log::info("saveNewTransactionImage:  $th");

            return response()->json('Server Error', 500);
        }
    }
}
