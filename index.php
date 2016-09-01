<?php

require_once 'vendor/autoload.php';
require_once 'config.php';

use Intervention\Image\ImageManager;

define('DS', DIRECTORY_SEPARATOR);

$action = null;
if(isset($_GET['action'])) {
    $action = $_GET['action'];
}

switch ($action) {
    case 'pipeThumbnail':
        pipeThumbnail();
        break;
    case 'pipeImage':
        pipeImage();
        break;
    case 'show':
        show();
        break;
    default:
        renderOverview();
}

function pipeImage() {
    $file = getFile();
    
    $manager = new ImageManager(array('driver' => 'imagick'));
    $img = $manager->make($file->getRealPath());
    echo $img->response();
}

function pipeThumbnail() {
    $file = getFile();
    $thumbname = 'cache' . DS . md5($file->getFilename());
    
    if(THUMB_GENERATION && !file_exists($thumbname)) {
        $manager = new ImageManager(array('driver' => 'imagick'));
        
        $img = $manager->make($file->getRealPath());    
        $img->resize(THUMB_SIZE_MAX_WIDTH, THUMB_SIZE_MAX_HEIGHT, function($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });
        $img->save($thumbname, THUMB_QUALITY);

        echo $img->response('jpg', THUMB_QUALITY);
    } else {
        if(file_exists($thumbname)) {
            $file = $thumbname;
        } else {
            $file = $file->getRealPath();
        }
        
        $handle = fopen($file, 'rb');
        header('Content-Type: image/jpg');
        header('Content-Length: ' . filesize($thumbname));
        fpassthru($handle);
    }
}

function show() {
    $dirs = getDirs();
    $dirix = getDirix();
    $filix = getFilix();
    
    if($dirix === false || $filix === false || $dirix >= count($dirs) || $dirix < 0) {
        return;
    }
    
    $cdir = $dirs[$dirix];
    $files = getFiles($cdir);
    if($filix >= count($files) || $filix < 0) {
        return;
    }
    
    echo getTwig()->render('show.twig', [
        'dirs' => $dirs,
        'next' => $filix < count($files) - 1,
        'prev' => $filix > 0,
        'dirix' => getDirix(),
        'filix' => getFilix()
    ]);
}

function renderOverview() {
    $dirs = getDirs();

    $files = [];
    if(getDirix() !== false) {
        $dirix = getDirix();
        if(isset($dirs[$dirix])) {
            $cdir = $dirs[$dirix];
            $files = getFiles($cdir);
        }
    }

    echo getTwig()->render('index.twig', [
        'dirs' => $dirs,
        'files' => $files,
        'dirix' => getDirix(),
        'filix' => getFilix()
    ]);
}

function getTwig() {
    $loader = new Twig_Loader_Filesystem('templates');
    $twig = new Twig_Environment($loader, [
        //'cache' => 'cache'
    ]);
    return $twig;
}

function getDirs() {
    $dirs = [];

    $it = new RecursiveDirectoryIterator(IMAGE_DIR);
    $it = new RecursiveIteratorIterator($it);

    foreach($it as $dir) {
        if($dir->getFileName() == '.' && count(getFiles($dir)) > 0) {
            $dirs[] = $dir;
        }
    }

    natcasesort($dirs);
    $dirs = array_filter($dirs, function($item) {
        return strlen($item) > strlen(IMAGE_DIR . DS) + 1;
    });
    $dirs = array_values($dirs);
    
    return $dirs;
}

function getFiles($dir) {
    $it = new FileSystemIterator($dir);
    $files = [];
    foreach($it as $file) {
        if($file->isDir()
            || (strtolower($file->getExtension()) !== 'jpg'
            && strtolower($file->getExtension()) !== 'jpeg')) {
            continue;
        }
        $files[] = $file;
    }
    natcasesort($files);
    return array_values($files);
}

function getFile() {
    $filix = getFilix();
    $dirix = getDirix();
    if($filix === false || $dirix === false) {
        return false;
    }
    
    $dirs = getDirs();
    if(!isset($dirs[$dirix])) {
        return false;
    }
    $cdir = $dirs[$dirix];
    $files = getFiles($cdir);
    if(!isset($files[$filix])) {
        return false;
    }
    
    return $files[$filix];
}

function getDirix() {
    if(isset($_GET['dirix']) && is_numeric($_GET['dirix'])) {
        return intval($_GET['dirix']);
    }
    return false;
}

function getFilix() {
    if(isset($_GET['filix']) && is_numeric($_GET['filix'])) {
        return intval($_GET['filix']);
    }
    return false;
}
