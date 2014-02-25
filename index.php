<?php
error_reporting(E_ERROR | E_PARSE);
require_once "google-api-php-client/src/Google_Client.php";
require_once "google-api-php-client/src/contrib/Google_DriveService.php";
require_once "google-api-php-client/src/contrib/Google_Oauth2Service.php";
session_start();

define('DRIVE_SCOPE', 'https://www.googleapis.com/auth/drive');

// Enable Google Drive APi and create a service account at https://code.google.com/apis/console/
define('SERVICE_ACCOUNT_EMAIL', '<<INSERT EMAIL ACCOUNT SERVICE>>@developer.gserviceaccount.com');
define('SERVICE_ACCOUNT_PKCS12_FILE_PATH', '<<INSERT FILE KEY>>-privatekey.p12');

define('EMAIL_SHARE', '<<INSERT EMAIL>>'); //User Google Drive account

/**
 * Build and returns a Drive service object authorized with the service accounts.
 *
 * @return Google_DriveService service object.
 */
function buildService() {
  $key = file_get_contents(SERVICE_ACCOUNT_PKCS12_FILE_PATH);
  $auth = new Google_AssertionCredentials(
      SERVICE_ACCOUNT_EMAIL,
      array(DRIVE_SCOPE),
      $key);
  $client = new Google_Client();
  $client->setUseObjects(true);
  $client->setAssertionCredentials($auth);
  return new Google_DriveService($client);
}

/**
 * Insert new file.
 *
 * @param Google_DriveService $service Drive API service instance.
 * @param string $title Title of the file to insert, including the extension.
 * @param string $description Description of the file to insert.
 * @param string $parentId Parent folder's ID.
 * @param string $mimeType MIME type of the file to insert.
 * @param string $filename Filename of the file to insert.
 * @return Google_DriveFile The file that was inserted. NULL is returned if an API error occurred.
 */
function insertFile($service, $title, $description, $parentId, $mimeType, $filename) {
  $file = new Google_DriveFile();
  $file->setTitle($title);
  $file->setDescription($description);
  $file->setMimeType($mimeType);

  // Set the parent folder.
  if ($parentId != null) {
    $parent = new ParentReference();
    $parent->setId($parentId);
    $file->setParents(array($parent));
  }

  try {
    $data = file_get_contents($filename);

    $createdFile = $service->files->insert($file, array(
      'data' => $data,
      'mimeType' => $mimeType,
    ));

    // Uncomment the following line to print the File ID
    //print 'File ID: %s' % $createdFile->getId();

    return $createdFile;
  } catch (Exception $e) {
    print "An error occurred: " . $e->getMessage();
  }
}

/**
 * Insert a new permission.
 *
 * @param Google_DriveService $service Drive API service instance.
 * @param String $fileId ID of the file to insert permission for.
 * @param String $value User or group e-mail address, domain name or NULL for
                       "default" type.
 * @param String $type The value "user", "group", "domain" or "default".
 * @param String $role The value "owner", "writer" or "reader".
 * @return Google_Permission The inserted permission. NULL is returned if an API
                             error occurred.
 */
function insertPermission($service, $fileId, $value, $type, $role) {
  $newPermission = new Google_Permission();
  $newPermission->setValue($value);
  $newPermission->setType($type);
  $newPermission->setRole($role);
  try {
    return $service->permissions->insert($fileId, $newPermission);
  } catch (Exception $e) {
    print "An error occurred: " . $e->getMessage();
  }
  return NULL;
}

/**
 * Retrieve a list of File resources.
 *
 * @param Google_DriveService $service Drive API service instance.
 * @return Array List of Google_DriveFile resources.
 */
function retrieveAllFiles($service) {
  $result = array();
  $pageToken = NULL;

  do {
    try {
      $parameters = array();
      if ($pageToken) {
        $parameters['pageToken'] = $pageToken;
      }
      $files = $service->files->listFiles($parameters);

      $result = array_merge($result, $files->getItems());
      $pageToken = $files->getNextPageToken();
    } catch (Exception $e) {
      print "An error occurred: " . $e->getMessage();
      $pageToken = NULL;
    }
  } while ($pageToken);
  return $result;
}


/*
 * Form Upload
 */ 
$service = buildService();
if(isset($_FILES['file_upload'])){
    $title = $_FILES['file_upload']['name'];
    $mimeType = $_FILES['file_upload']['type'];
    $filename = $_FILES['file_upload']['name'];
    $uploadfile = './temp/' . basename($_FILES['file_upload']['name']);
    //move to server before sent to Drive
    move_uploaded_file($_FILES['file_upload']['tmp_name'], $uploadfile);
    //send to Drive
    $file = insertFile($service, $title, 'Uploaded via API', null, $mimeType, $uploadfile);
    
    //set permission to public
    $result = insertPermission($service,$file->id, EMAIL_SHARE,'user','writer');
    $result = insertPermission($service,$file->id, 'anyone','anyone','reader');
    if(isset($result)){
        $msg[0] = true;
        $msg[1] = 'Arquivo enviado com sucesso!';
    }else{
        $msg[0] = false;
        $msg[1] = 'Erro ao enviar arquivo, tente novamente';
    }
}else{
    /* TODO */
    // List all files
    //$result = retrieveAllFiles(buildService());
}
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <title>Nuovo Uploader</title>

    <!-- Bootstrap core CSS -->
    <link href="css/bootstrap.css" rel="stylesheet">
  </head>
  
  <body>

    <div class="container">

      <div class="masthead">
        <h3 class="text-muted">Nuovo Uploader</h3>
      </div>
        <?php
        if(isset($msg) && is_array($msg)){
            if($msg[0]){
                echo '<div class="alert alert-success">
                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>'.$msg[1].'<p>
                <a href="'.$file->alternateLink.' target="_blank">
                    <img src="'.$file->iconLink.'"> Link para o arquivo
                </a><p></div>';
            }else{
                echo '<div class="alert alert-danger"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>'.$msg[1].'</div>';
            }
        }
        ?>
      <!-- Jumbotron -->
      <div class="jumbotron">
        <h1>Selecione seu arquivo para enviar:</h1>
        <form action="<?php echo $_SERVER['PHP_SELF']?>" method="POST" enctype="multipart/form-data">
        <p class="lead">
            <label> 
                <input type="file" id="file_upload" name="file_upload">
            </label>
        </p>
        <p><button class="btn btn-lg btn-success"  type="submit">Enviar &raquo;</button></p>
        </form>
      </div>
      <!-- Site footer -->
      <div class="footer">
        <p><a href="http://www.nuovo.com.br/">Nuovo Design</a></p>
      </div>

    </div> <!-- /container -->

    <script src="//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
  </body>
</html>