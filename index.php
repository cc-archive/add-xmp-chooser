<?php

// Uses code from: https://github.com/cc-archive/xmp-jpeg-php/blob/master/jpeg-xmp-embed.php

require_once(__DIR__ . '/vendor/autoload.php');

use Monolog\Logger;
use PHPExiftool\Writer;
use PHPExiftool\Driver\Metadata\Metadata;
use PHPExiftool\Driver\Metadata\MetadataBag;
use PHPExiftool\Driver\Tag\XMPCc;
use PHPExiftool\Driver\Tag\XMPDc;
use PHPExiftool\Driver\Value\Mono;

require_once('licenses.php');

// For convenience, not security
ini_set("session.cookie_lifetime", "1200");

$logger = new Logger('exiftool');

function licenseSelected ($index) {
    if (isset($_SESSION['license']) && $_SESSION['license'] == $index) {
        echo 'selected';
    }
}

function valueInSession ($key) {
    if (isset($_SESSION[$key])) {
        return $_SESSION[$key];
    }
    return '';
}

function tidySessionForNextUpload () {
   $attributes = ['filepath', 'workTitle', 'attributionUrl'];
    array_walk($attributes,
               function ($key) {
                   unset($_SESSION[$key]);
               });
}

function storeAttributionInSession () {
    //FIXME: HORRIFIC SECURITY HOLE. FIX FOR ANY KIND OF RELEASE
    $attributes = ['license', 'workTitle', 'attributionName', 'attributionUrl'];
    array_walk($attributes,
               function ($key) {
                   if (isset($_POST[$key])) {
                       $_SESSION[$key] = $_POST[$key];
                   }
               });
};

function applyLicense () {
    global $logger;
    //FIXME: Dispatch on file type, complain on invalid file type
    $licenseNumber = $_SESSION['license'];
    $licenseURL = license_url_for_num($licenseNumber, '4.0');
    $title = $_SESSION['workTitle'];
    $author = $_SESSION['attributionName'];
    $authorURL = $_SESSION['attributionUrl'];
    $metadatas = new MetadataBag();
    $metadatas->add(new Metadata(new XMPCc\License(),
                                 new Mono($licenseURL)));
    if ($title) {
      $metadatas->add(new Metadata(new XMPDc\Title(),
                                   new Mono($title)));
    }
    if ($author) {
      $metadatas->add(new Metadata(new XMPCc\AttributionName(),
                                   new Mono($author)));
    }
    if ($authorURL) {
      $metadatas->add(new Metadata(new XMPCc\AttributionURL(),
                                   new Mono($authorURL)));
    }
    $Writer = Writer::create($logger);
    // Doesn't erase existing metadata (that can be set if needed)
    if ($Writer->write($_SESSION['filepath'], $metadatas) !== null) {
        $_SESSION['flash'] = 'License applied! Ready for download: '
                           . '<a href="' . $_SESSION['filepath']
                           . '" target="_blank" download><strong>'
                           . basename($_SESSION['filepath'])
                           . '</strong></a>';
    } else {
        $_SESSION['flash'] = "Couldn't write file out with new metadata.";
    }
}

function handleFileUpload () {
    unset($_SESSION['filepath']);

    if (! isset($_FILES['upfile']['error'])
        || is_array($_FILES['upfile']['error'])) {
        throw new RuntimeException('Invalid parameters.');
    }

    // Check $_FILES['upfile']['error'] value.
    switch ($_FILES['upfile']['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            throw new RuntimeException('No file sent.');
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            throw new RuntimeException('Exceeded filesize limit.');
        default:
            throw new RuntimeException('Unknown errors.');
    }

    // You should also check filesize here.
    if ($_FILES['upfile']['size'] > 1000000) {
        throw new RuntimeException('Exceeded filesize limit.');
    }

    // DO NOT TRUST $_FILES['upfile']['mime'] VALUE !!
    // Check MIME Type by yourself.
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    if (false === $ext = array_search(
        $finfo->file($_FILES['upfile']['tmp_name']),
        array(
            'jpg' => 'image/jpeg',
            //'png' => 'image/png',
            //'gif' => 'image/gif',
        ),
        true
    )) {
        throw new RuntimeException('Invalid file format.');
    }

    // You should name it uniquely.
    // DO NOT USE $_FILES['upfile']['name'] WITHOUT ANY VALIDATION !!
    // On this example, obtain safe unique name from its binary data.
    $filepath = sprintf('./uploads/%s.%s',
                        sha1_file($_FILES['upfile']['tmp_name']),
                        $ext);
    if (! move_uploaded_file($_FILES['upfile']['tmp_name'], $filepath)) {
        throw new RuntimeException('Failed to move uploaded file.');
    }
    $_SESSION['filepath'] = $filepath;
    $_SESSION['flash'] = 'File is uploaded successfully.';
    error_log('ok!');
}

try {
    $alert_class = 'alert-info';
    session_start();
    if (isset($_POST['license'])) {
        if (isset($_POST['cancel'])) {
            tidySessionForNextUpload();
        } else {
            unset($_SESSION['flash']);
            storeAttributionInSession ();
            applyLicense();
        }
    } elseif (isset($_FILES['upfile'])) {
        unset($_SESSION['flash']);
        handleFileUpload ();
    }
} catch (RuntimeException $e) {
    $alert_class = 'alert-danger';
    $_SESSION['flash'] = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
    <title>Embed XMP In Images</title>

    <!-- Bootstrap -->
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css" rel="stylesheet">

    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
      <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->

  </head>
  <body>
    <div class="container">
      <h1>Let's add XMP!</h1>
      <i>But just to JPEGs at the moment...</i>
    <hr>

    <?php if (isset($_SESSION['flash'])) {?>
    <div class="alert <?= $alert_class; ?>"><?= $_SESSION['flash']; ?></div>
    <?php } ?>
    <div class="image-upload-div">
      <?php if (isset($_SESSION['filepath'])) { ?>
        <img id="img1" class="uploaded-image"
             src="<?= $_SESSION['filepath']; ?>">
      <?php } else { ?>
        <form action="index.php" method="post" enctype="multipart/form-data">
          <div class="form-group">
            <label for="fileToUpload">Select image to upload:</label>
            <input type="file" name="upfile" id="fileToUpload"
                   class="form-control" accept=".jpg">
            <p class="help-block">Select a JPEG file to upload and add metadata to.</p>
          </div>
          <button type="submit" class="btn btn-primary">Upload Image</button>
        </form>
      <?php } ?>
    </div>
    <?php if (isset($_SESSION['filepath'])) { ?>
      <div id="attribution-info">
        <?php if (isset($_SESSION['flash'])) { ?>
          <br><div
            class="alert <?= $alert_class; ?>"><?= $_SESSION['flash']; ?></div>
        <?php
          unset($_SESSION['flash']);
        } ?>
        <h2>Attribution</h2>
        <form action="index.php" method="post">
          <div class="form-group">
            <label for="license">License of Work</label>
            <select name="license" id="license" class="form-control">
              <option
                <?php licenseSelected(4); ?>
                value="4">Creative Commons Attribution</option>
              <option
                <?php licenseSelected(5); ?>
                value="5">Creative Commons Attribution-ShareAlike</option>
              <option
                <?php licenseSelected(2); ?>
                value="2">Creative Commons Attribution-NonCommercial</option>
              <option
                <?php licenseSelected(1); ?>
                value="1">Creative Commons
                Attribution-NonCommercial-ShareAlike</option>
              <option
                <?php licenseSelected(6); ?>
                value="6">Creative Commons Attribution-NoDerivs</option>
              <option
                <?php licenseSelected(3); ?>
                value="3">Creative Commons
                Attribution-NonCommercial-NoDerivs</option>
              <option
                <?php licenseSelected(0); ?>
                value="0">Creative Commons Zero/Public Domain</option>
            </select>
            <p class="help-block">The license you are placing the work under.</p>
          </div>
          <div class="form-group">
            <label for="workTitle">Title of Work</label>
            <input type="text" inputmode="latin-text" class="form-control"
                   id="workTitle" name="workTitle" placeholder="Untitled"
                   value="<?= valueInSession('workTitle'); ?>">
            <p class="help-block">The title of the work you are licensing.</p>
          </div>
          <div class="form-group">
            <label for="attributionName">Attribute work to name</label>
            <input type="text" inputmode="latin-name" class="form-control"
                   id="attributionName" name="attributionName"
                   placeholder="Matt Lee"
                   value="<?= valueInSession('attributionName'); ?>">
            <p class="help-block">The name of the person who should receive attribution for the work. Most often, this is the author.</p>
          </div>
          <div class="form-group">
            <label for="attributionUrl">Attribute work to URL</label>
            <input type="url" inputmode="url" class="form-control"
                   id="attributionUrl" name="attributionUrl"
                   placeholder="https://example.com/mattl/untitled"
                   value="<?= valueInSession('attributionUrl'); ?>">
            <p class="help-block">The URL to which the work should be attributed. For example, the work's page on the author's site.</p>
          </div>
          <button type="submit" name="submit"
                  class="btn btn-primary">Add metadata and download</button>
          <button type="submit" name="cancel" class="btn btn-default">Upload another image</button>
        </form>
      </div>
      <?php } ?>
      <hr>
      </div>

    <!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
    <!-- Include all compiled plugins (below), or include individual files as needed -->
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/js/bootstrap.min.js"></script>
  </body>
</html>
