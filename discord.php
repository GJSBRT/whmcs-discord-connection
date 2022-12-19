<?php

$client_id = "";
$secret_id = "";
$scopes = "identify email";
$domainurl = "https://billing.example.com";
$guild_id = 000000000000000000;
$role_id = 000000000000000000;
$bot_token = "";


use WHMCS\Authentication\CurrentUser;
use WHMCS\ClientArea;

define('CLIENTAREA', true);

require __DIR__ . '/init.php';

$ca = new ClientArea();
$ca->setPageTitle('Discord Connection');
$ca->initPage();

$currentUser = new CurrentUser();
$client = $currentUser->client();

if ($client) {    
    if(isset($_GET['code'])) {  

        /* Get user access token */
        $ch = curl_init('https://discord.com/api/oauth2/token');
        curl_setopt_array($ch, array(
            CURLOPT_HTTPHEADER     => array('Authorization: Bearer '.$_GET['code']),
            CURLOPT_VERBOSE        => 1,
            CURLOPT_CONTENT_LENGTH => 0,
            CURLOPT_POST           => 1,    
            CURLOPT_SSL_VERIFYPEER => 0
        ));
        curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=authorization_code&code=".$_GET['code']."&redirect_uri={$domainurl}/discord.php&client_id={$client_id}&client_secret={$secret_id}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $data = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch); 
        $kaas = json_decode($data);

        if ($kaas->access_token) {
            /* Get user ids */
            $ch = curl_init('https://discord.com/api/users/@me');
            curl_setopt_array($ch, array(
                CURLOPT_HTTPHEADER     => array('Authorization: Bearer '.$kaas->access_token),
                CURLOPT_VERBOSE        => 1,
                CURLOPT_CONTENT_LENGTH => 0,   
                CURLOPT_SSL_VERIFYPEER => 0
            ));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $data = curl_exec($ch);
            $error = curl_error($ch);
            curl_close($ch);
            $kaas = json_decode($data);

            /* Add Discord ID to client profile */
            $command = 'UpdateClient';
            $customfields = array('discord' => $kaas->id);
            $postData = array(
                'clientid' => $client->id,
                'customfields' => base64_encode(serialize($customfields))
            );

            $results = localAPI($command, $postData);

            /* Add role to user */
            $currentUser = new CurrentUser();
            $authUser = $currentUser->user();

            $command = 'GetClientsDetails';
            $postData = array(
                'clientid' => $authUser->id,
                'stats' => true,
            );
            $results = localAPI($command, $postData);

            if ($results['stats']['productsnumactive'] > 0) {
                $url = "https://discord.com/api/v9/guilds/{$guild_id}/members/{$kaas->id}/roles/{$role_id}"; 
                $curl = curl_init();

                $headers = [
                    'Accept: application/json',
                    'Content-Type: application/json',
                    'Authorization: Bot ' . $bot_token
                ];

                curl_setopt($curl, CURLOPT_URL, $url);
                curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
                curl_setopt($curl, CURLOPT_POSTFIELDS, []);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                $response = curl_exec($curl);
                curl_close($curl);

                $ca->assign('message', "Discord Linked Successfully");
            }
        } else {    
            $ca->assign('message', "This Link has Expired");
        }
    } else {
        header('Location: https://discordapp.com/oauth2/authorize?response_type=code&client_id=' . $client_id . '&redirect_uri=' . $domainurl . '/discord.php&scope=' . $scopes );
    }
}

$ca->setTemplate('discord');

$ca->output();
