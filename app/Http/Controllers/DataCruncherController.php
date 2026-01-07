<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;
use GuzzleHttp\Client;

class DataCruncherController extends Controller
{
    /**
     * Mengambil dan memproses data dari website eksternal.
     */
    public function crunch(Request $request)
    {
        // dd($request);
        $url = 'https://sepa-monitor.ascp.alibaba-inc.com/backend/mcl/fulfillmentStatusUpdateQuery';
        $cookie = "an=aditya.briananto; lg=true; sg=o17; TB_GTA=%7B%22pf%22%3A%7B%22cd%22%3A%22.alibaba-inc.com%22%2C%22dr%22%3A0%7D%2C%22uk%22%3A%225cdac617b3a4327b0937a545%22%7D; bs_n_lang=en_US; ALIPAYCHAIRBUCJSESSIONID=32e132a2-6ef1-400b-ab67-44ea8ad49906; ordv=FRpobJqmBw..; receive-cookie-deprecation=1; _CHIPS-ALIPAYCHAIRBUCJSESSIONID=32e132a2-6ef1-400b-ab67-44ea8ad49906; SSO_LANG_V2=EN; currentRegionId=rg-sg; ck2=68e82cb0904430006bbb974ffe9b89d7; teambition_private_sid_aone=eyJ1aWQiOiI1Y2RhYzYxN2IzYTQzMjdiMDkzN2E1NDUiLCJhdXRoVXBkYXRlZCI6MTU1Nzg0MTQzMTMzNiwidXNlciI6eyJfaWQiOiI1Y2RhYzYxN2IzYTQzMjdiMDkzN2E1NDUiLCJuYW1lIjoiQnJpYW5hbnRvLCBBZGl0eWEiLCJlbWFpbCI6ImFkaXR5YS5icmlhbmFudG9AbGF6YWRhLmNvLmlkIiwiYXZhdGFyVXJsIjoiaHR0cHM6Ly93b3JrLmFsaWJhYmEtaW5jLmNvbS9waG90by8xMjU3NTcuNDB4NDAuanBnIiwicmVnaW9uIjoiIiwibGFuZyI6IiIsImlzUm9ib3QiOmZhbHNlLCJvcGVuSWQiOiIxMjU3NTciLCJwaG9uZUZvckxvZ2luIjoiIiwib3JnYW5pemF0aW9uSWQiOiIifSwibG9naW5Gcm9tIjoiYWxpYmFiYSIsImxvZ2luVGltZSI6MCwiaWF0IjoxNzY0NTUxMDI0fQ==; teambition_private_sid_aone.sig=cexWdSILEtJn7LNJIG8eI4O3pgU; emplId=125757; SSO_EMPID_HASH_V2=8304644e6944dcfa059994e1abcb8a52; SSO_BU_HASH_V2=d471f5c36dd8600fdc08d99082107140; rtk=aZmq0gcRbPoSwKD45yt7CffwSWs0xnMkAZOsrm8p1Zidxn4c5yR; c_token=c30d66dc46e83eb3ac3b5dfd932d1a2c; lvc=sArg3JKnyp1kpQ%3D%3D; cna=hgjRIWGONAECASp4STB3UB1Y; SSO_LANG_V2=EN; xlly_s=1; x_mini_wua=noid; x_sign=noid; x_umt=noid; sgcookie=E100tLoBIS8c2y9Oc5n6UabdfhMk6nwkxw+CMIenecHih0sUx8lTTu/JohIbs8uC+v2s7bzwL7rh1n6cf75eFCcDd/cnIPgQnEUOkh3+t7Hn6EM=; SSO_REFRESH_TOKEN=buc4270d8bf3e7746db9c06951d287000d5c9a00; _m_h5_c=c19f2410612c3901b236b5d851981f20_1766980128028%3Bd3963988bd6b29c680c93a0db2ac2d24; isg=BLi41kJ1JUrLpUj05BY6uUxSiWBKIRyrLavwbPIpw_IlDVn3mjUxOvbuwR29XdSD; sepa-monitor_USER_COOKIE=4646CDE086DAC952CA0CEB08E39A6112BA085C51FB33EBA796A7F3D1D94A4B04FD1530E2E7FE9E0ECB13D80CA3E73207DA24F34F4E7B9F163538B47C1B82F381AF434F4AAD4324E86F6FDB6D1B4990BD3DDDC6D66ECB42219A9EFC9E74ABF44F7FEAF696DAF2C5273BCA5A414577D533897B5D5D011E0772234437EBF5EC41F9F3DBE74214C0180F558259E575251169094264AED2853530FA9E9BB3EC064F26214F9012F831A4F61A6C6F07953A15395E44D23FC1B30F6C5502534DB1465DCA8D8474DE25DF8B31CF52DF150BDDBD54EE427183C022B4FADFACE069265BB5C8C9AFACD25C2F989AB113C2743D82D1EF9B51D7831CB77656EFCD9F382DEA59741A6E63F105A4598B7D0B160F2C9056E96670CECDD7F6E7F105A11D26D6A525B1BC90F35B7B2808561C2720B6D0CD997AAF99CBD2BF1E6592559D1FCFC0C2300C871DD1AD30307298DBD4D559EF103582396A8E361CCC9902226AF37DEE6268A0A93AC4F4A46A299F50A4E3346F90B5DB7231D0386DABF6FED761E0E64F247E99ADF93A3125F91EE11D38FAB947C78CDF0A0DD75B449127BD0AA48BD0B108FC1636EADCFFB30FC62DF73604BC52B4D0473EF389F2B1A4799EC849ECF497D38B32; SSO_EMPID_HASH_V2=8304644e6944dcfa059994e1abcb8a52; SSO_BU_HASH_V2=d471f5c36dd8600fdc08d99082107140; sepa-monitor_SSO_TOKEN_V2=0B04270F8D0C10B3DA4B26EAD04E95F6338083F73F72CE48B0239B234BF65E8D160A402D07E0E057372EF8F4D458273386424FFAB7B30D46AA737D7DD9B7CEB6; tfstk=gtpKJI_D9cELEgpdjHliruQxXFoMpfxebe-bEab3NFL9VEQoYHtHyQLHcwjkVX75y37iYTAnaTCJrNKhZ2-5yULG76vHr96JVebwZWYo4YnJ7FdhZabFVTBeIB2l-2WJVEX-oqDmnH-FLTgmo_rtB3BfcaNQraw1fTjJW3yk-H-FUlJ0N-8XYYdumD65FU611gjlFg15NcC1VNwCVJs75cQN5zs5FgN15MjfFaT5AcB1qg65AU1IXhGMD4Q_Aa2JtZCZlzzQCRw6BMCdyWbQeOvQn_7j1Ny4awhl6Z6cW8wWBMKJp4j8dxQ6tQ9hCpg_uOt2sesOHYERXIK1dMLr3JWBchO59Lhu0aR9fIf9_kmFXpKveiCa5DQyKepRTKg0QwO9QLBHs2ecz_OMLsJn5JBXgHXHNFi7Fad1VgyvnKLSurbAqWitX7PPOG8GdcEgWmkAWGQmjuFza1TOXZmtX7PPOGSOocq8a756W";

        $data = [
            "salesOrderNumber" => 581862629402903862,
            "offset" => "0",
            "line" => "11",
            "from" => 1766891629,
            "to" => 1766978029
        ];

        $client = new Client();
        $result  =  new \GuzzleHttp\Psr7\Request(
            'POST',
            $url,
            [
                'Content-Type' => 'application/json',
                'Cookie' => $cookie
            ],
            json_encode($data)
        );
        $response = $client->send($result);
        // dd($response);
        // dd(json_decode((string)$response->getBody(), true));
        $jsonResult = json_decode((string)$response->getBody(), true);
        // dd($jsonResult);
        foreach($jsonResult as $json) {
            $jsonResultRaw = $json['request'];
            dd($jsonResultRaw);
            // $jsonResultFormated = json_decode($jsonResultRaw);
            // dd(json_decode($jsonResultFormated->body));
        }
        // try {
        //     // 1. Ambil konten HTML menggunakan Laravel Http Client (Guzzle)
        //     $response = Http::withHeaders([
        //         'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        //     ])->get($url);

        //     if ($response->failed()) {
        //         return response()->json(['error' => 'Gagal mengakses website'], 500);
        //     }

        //     $html = $response->body();
        //     $crawler = new Crawler($html);
        //     dd($crawler);
        //     // 2. Crunching Data (Filter spesifik elemen HTML)
        //     // Misal: Kita ingin mengambil semua judul dalam tag <h2 class="post-title">
        //     $data = $crawler->filter('h2')->each(function (Crawler $node) {
        //         return [
        //             'title' => trim($node->text()),
        //             'link'  => $node->filter('a')->attr('href'),
        //         ];
        //     });
        //     dd($data);
        //     // 3. Post-Processing (Misal: Filter kata kunci tertentu)
        //     $filteredData = collect($data)->filter(function ($item) {
        //         return str_contains(strtolower($item['title']), 'laravel');
        //     })->values();

        //     return response()->json([
        //         'status' => 'success',
        //         'source' => $url,
        //         'count'  => count($filteredData),
        //         'results' => $filteredData
        //     ]);

        // } catch (\Exception $e) {
        //     return response()->json(['error' => $e->getMessage()], 500);
        // }
    }
}
