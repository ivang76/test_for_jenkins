<?php

require_once __DIR__ . '/vendor/autoload.php';

define('APPLICATION_NAME', 'Interio script');
//define('CREDENTIALS_PATH', '~/.credentials/sheets.googleapis.com-php-quickstart.json');
define('CREDENTIALS_PATH', __DIR__ . '/credentials/sheets.googleapis.com-php-quickstart.json');
define('CLIENT_SECRET_PATH', __DIR__ . '/client_secret.json');

date_default_timezone_set('Europe/Rome');

$step=4;  //number of records to create at every loop before to update the sheet
$spreadsheetId = "1k9o1kvC3fvA9XT_sbfGjGo572y7_Kiw70Qf1pLBy2-Y";  //id of the remote target sheet to compile

//
// scopes info: https://developers.google.com/resources/api-libraries/documentation/sheets/v4/java/latest/com/google/api/services/sheets/v4/SheetsScopes.html
//
// If modifying these scopes, delete your previously saved credentials
// at ~/.credentials/sheets.googleapis.com-php-quickstart.json
define('SCOPES', implode(' ', array(
  Google_Service_Sheets::SPREADSHEETS)
));

if (php_sapi_name() != 'cli') {
  throw new Exception('This script must be run on the command line.');
}

/**
 * Returns an authorized API client.
 * @return Google_Client the authorized client object
 */
function getClient() {
  $client = new Google_Client();
  $client->setApplicationName(APPLICATION_NAME);
  $client->setScopes(SCOPES);
  $client->setAuthConfigFile(CLIENT_SECRET_PATH);
  $client->setAccessType('offline');

  // Load previously authorized credentials from a file.
  $credentialsPath = expandHomeDirectory(CREDENTIALS_PATH);
  if (file_exists($credentialsPath)) {
    $accessToken = file_get_contents($credentialsPath);
  } else {
    // Request authorization from the user.
    $authUrl = $client->createAuthUrl();
    printf("Open the following link in your browser:\n%s\n", $authUrl);
    print 'Enter verification code: ';
    $authCode = trim(fgets(STDIN));

    // Exchange authorization code for an access token.
    $accessToken = $client->authenticate($authCode);

    // Store the credentials to disk.
    if(!file_exists(dirname($credentialsPath))) {
      mkdir(dirname($credentialsPath), 0700, true);
    }
    file_put_contents($credentialsPath, $accessToken);
    printf("Credentials saved to %s\n", $credentialsPath);
  }
  $client->setAccessToken($accessToken);

  // Refresh the token if it's expired.
  if ($client->isAccessTokenExpired()) {
    $client->refreshToken($client->getRefreshToken());
    file_put_contents($credentialsPath, $client->getAccessToken());
  }
  return $client;
}

/**
 * Expands the home directory alias '~' to the full path.
 * @param string $path the path to expand.
 * @return string the expanded path.
 */
function expandHomeDirectory($path) {
  $homeDirectory = getenv('HOME');
  if (empty($homeDirectory)) {
    $homeDirectory = getenv("HOMEDRIVE") . getenv("HOMEPATH");
  }
  return str_replace('~', realpath($homeDirectory), $path);
}

// Get the API client and construct the service object.
$client = getClient();
$service = new Google_Service_Sheets($client);

$range = "Sessel!A2:F";
$response = $service->spreadsheets_values->get($spreadsheetId, $range);
$values = $response->getValues();

$target_products=array();
    
if (count($values) == 0) {
  print "No data found.\n";
} else {
  print "Name, Major:\n";
  foreach ($values as $row) {
    // Print columns A and E, which correspond to path and target_keywords
    printf("%s, %s\n", $row[0], $row[1]);
    $tprod=["url"=>$row[0], "tkey"=>$row[1] ];
    array_push( $target_products, $tprod );
  }
}

print_r($target_products);

//*******************************************************************************
echo ' download product sitemap from Interio <br/>';

$url = "http://www.interio.ch/product.xml.gz";
$zipFile = "product.gz"; // Local Zip File Path
$zipResource = fopen($zipFile, "w");
// Get The Zip File From Server
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_FAILONERROR, true);
curl_setopt($ch, CURLOPT_HEADER, 0);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_AUTOREFERER, true);
curl_setopt($ch, CURLOPT_BINARYTRANSFER,true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); 
curl_setopt($ch, CURLOPT_FILE, $zipResource);
$page = curl_exec($ch);
if(!$page) {
 echo "Error :- ".curl_error($ch);
}
curl_close($ch);

echo ' unzip product sitemap <br/>';

// Raising this value may increase performance
$buffer_size = 4096; // read 4kb at a time
$out_file_name = str_replace('.gz', '', $zipFile);

// Open our files (in binary mode)
$file = gzopen($zipFile, 'rb');
$out_file = fopen($out_file_name, 'wb');

// Keep repeating until the end of the input file
while(!gzeof($file)) {
    // Read buffer-size bytes
    // Both fwrite and gzread and binary-safe
    fwrite($out_file, gzread($file, $buffer_size));
}

// Files are done, close files
fclose($out_file);
gzclose($file);

echo ' loop on product sitemap, crowl meta and show infos <br/>';
$xml = simplexml_load_file('product') or die("Error: Cannot create object");

$cc=0;
$cinserts=0;
$products_infos=array();

foreach($xml->url as $item)
{
    print("\n");
    print($cc);
    print("\n");
    
    $url=(string)$item->loc;
    if (strpos($url, '/fr/') !== false) continue;  //for now let's filter only the german url
    $cc++;
    
    $code= array_pop(explode('.', $url));
    $tags = get_meta_tags($url);
    $keyw=$tags['keywords'];
    $headers = @get_headers($url);
    $status=$headers[0];

    $target_keyword=""; 
    foreach ($target_products as $item) {
            if (strpos($item['url'], $code ) !== false) { 
                $target_keyword=$item['tkey'];
            }     
    }

    $timedate = date('l jS \of F Y h:i:s A');
    
    $info_obtained=[ "url"=> $url, "keyword" => $keyw, "target_keyword" => $target_keyword, "status" => $status, "timedate" => $timedate];
    
    array_push( $products_infos, $info_obtained );
    
    $cinserts++;
    
    print_r($products_infos);
    
    if ($cc>10) break;   
    
    
}


//#####################################################################################

function update_sheet() {
    global $cc;
    global $products_infos;
    global $service;
    global $spreadsheetId;
    global $step;
    
    $rows_to_insert= array();
    print("*********************\n ");    
    print_r($products_infos);
        
    $c=0;
    foreach( $products_infos as $product) {
        print("##################\n ");
        print($c);
        print("\n");
        print_r($product);
        $c++;
        $row=array( array(
                'userEnteredValue' => array('numberValue' => $cc-$step+$c),
                //'userEnteredFormat' => array('backgroundColor' => array('red' => 1))
              ), array(            
                'userEnteredValue' => array('stringValue' => $product['url']),
                //'userEnteredFormat' => array('backgroundColor' => array('red' => 1))
              ), array(
                'userEnteredValue' => array('stringValue' => $product['keyword']),
                //'userEnteredFormat' => array('backgroundColor' => array('blue' => 1))
              ), array(
                'userEnteredValue' => array('stringValue' => $product['target_keyword']),
                //'userEnteredFormat' => array('backgroundColor' => array('green' => 1))
              ), array(
                'userEnteredValue' => array('stringValue' => $product['status']),
                //'userEnteredFormat' => array('backgroundColor' => array('green' => 1))
              ), array(
                'userEnteredValue' => array('stringValue' => $product['timedate'] )
                //'userEnteredFormat' => array('backgroundColor' => array('green' => 1))
              ) );


        array_push($rows_to_insert, array('values' => $row) );
    }


    $requests[] = new Google_Service_Sheets_Request(array(
      'updateCells' => array(
        'start' => array(
          'sheetId' => 0,
          'rowIndex' => $cc-$step+1,
          'columnIndex' => 0
        ),
        'rows' => $rows_to_insert,

        //'fields' => 'userEnteredValue,userEnteredFormat.backgroundColor'
        'fields' => 'userEnteredValue'

      )
    ));


    
    try {
        $batchUpdateRequest = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest(array(
          'requests' => $requests
        ));

        $service->spreadsheets->batchUpdate($spreadsheetId, $batchUpdateRequest);
    }
    catch (Exception $exc) {
        print("ERROR ".$exc);
    }   
    

    unset($rows_to_insert);

    
    sleep(2);  //I noticed that with a so long number of cells to write/update, after a while it seems
               //that from a random order the scripts is no more able to store because some previous data
               //is not yet saved, so let' me try to add also a little pause before run the other part...
}


//*******************************************************************************




?>



