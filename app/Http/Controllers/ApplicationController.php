<?php

namespace App\Http\Controllers;

use App\Models\Application;
use Illuminate\Http\Request;

class ApplicationController extends Controller
{
    public function getAllAplicationList()
    {
        try {
            $application = Application::all();
            return response()->json($application, 200);
        } catch (\Throwable $th) {
            \Log::info("Throwable $th");
            return response()->json('Server Error', 500);
        }
    }
    public function getAllActiveAplicationList()
    {
        try {
            $application = Application::where('is_active', true)->get();
            return response()->json($application, 200);
        } catch (\Throwable $th) {
            \Log::info("Throwable $th");
            return response()->json('Server Error', 500);
        }
    }
    public function getAllActiveAplicationListByOS($os)
    {
        try {
            $application = Application::where('is_active', true)
                ->where('os', $os)
                ->get();
            return $application;
        } catch (\Throwable $th) {
            \Log::info("Throwable $th");
            return response()->json('Server Error', 500);
        }
    }
    public function getApplicationOSes() {
        try {
            $application = Application::select('os')
            ->where('is_active', true)
            ->orderby('os')
            ->distinct()

            ->get();
            return $application;
        } catch (\Throwable $th) {
            \Log::info("Throwable $th");
            return response()->json('Server Error', 500);
        }
    }
    public function getActiveAplicationListByName($name)
    {
        try {
            $application = Application::where('is_active', true)
                ->where('name', $name)
                ->first();
            return response()->json($application, 200);
        } catch (\Throwable $th) {
            \Log::info("Throwable $th");
            return response()->json('Server Error', 500);
        }
    }
    public function getActiveAplicationByID($id)
    {
        try {
            $application = Application::where('is_active', true)
                ->where('id', $id)
                ->first();
            return $application;
        } catch (\Throwable $th) {
            \Log::info("Throwable $th");
            return response()->json('Server Error', 500);
        }
    }
    public function createNewApplication(Request $request)
    {
        try {
            $application = new Application();
            $application->name = $request->name;
            $application->download_link = $request->download_link;
            $application->file_src = $request->file_src;
            $application->os = $request->os;
            $application->how_to_use = $request->how_to_use;
            $application->youtube_link = $request->youtube_link;
            $application->is_active = $request->is_active == "true" ||$request->is_active == 1 ? true : false   ;
            $application->description = $request->description;
            $application->save();
            return response()->json($application, 200);

        } catch (\Throwable $th) {
            \Log::info("Throwable $th");
            return response()->json('Server Error', 500);
        }
    }
    public function updateApplication(Request $request)
    {
        try {
            $application = Application::findOrFail($request->id);
            $application->name = $request->name;
            $application->download_link = $request->download_link;
            $application->file_src = $request->file_src;
            $application->os = $request->os;
            $application->how_to_use = $request->how_to_use;
            $application->youtube_link = $request->youtube_link;
            $application->is_active = $request->is_active == "true" ||$request->is_active == 1 ? true : false   ;
            $application->description = $request->description;
            $application->update();
            return response()->json($application, 200);

        } catch (\Throwable $th) {
            \Log::info("Throwable $th");
            return response()->json('Server Error', 500);
        }
    }
    public function deleteApplication($id)
    {
        try {
            $application = Application::findOrFail($id);
            $application->delete();
            return response()->json(true, 200);

        } catch (\Throwable $th) {
            \Log::info("Throwable $th");
            return response()->json('Server Error', 500);
        }
    }
}
