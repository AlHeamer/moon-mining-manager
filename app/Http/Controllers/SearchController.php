<?php

namespace App\Http\Controllers;

use App\Classes\EsiConnection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SearchController extends Controller
{
    /**
     * @throws \Exception
     */
    public function search(Request $request)
    {
        $esi = new EsiConnection;
        $conn = $esi->getConnection();

        $result = $conn->setQueryString([
            'categories' => 'character',
            'search' => $request->q,
            'strict' => 'true',
        ])->invoke('get', '/search/');

        Log::info('SearchController: results returned by /search query', [
            'result' => $result,
        ]);

        // If there are more than ten matching results, we want them to keep typing.
        if (isset($result) && isset($result->character)) {
            $character_id = $result->character[0];
            $character = $conn->invoke('get', '/characters/{character_id}/', [
                'character_id' => $character_id,
            ]);
            $character->id = $character_id;
            $portrait = $conn->invoke('get', '/characters/{character_id}/portrait/', [
                'character_id' => $character_id,
            ]);
            $character->portrait = $portrait->px128x128;
            $corporation = $conn->invoke('get', '/corporations/{corporation_id}/', [
                'corporation_id' => $character->corporation_id,
            ]);
            $character->corporation = $corporation->name;
            return $character;
        } else {
            return 'No matches returned, API may be unreachable...';
        }

    }
}
