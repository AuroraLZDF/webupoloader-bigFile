<?php

if (version_compare(PHP_VERSION, '7.0.0') < 0) {
    echo json_encode(['code' => 2, 'mes' => '您的PHP版本过低，请使用PHP7以上版本！']);
    exit;
}

include_once('./FileUpload.php');

define('ROOT', dirname(__DIR__));
define('UPLOAD_PATH', ROOT . '/upload');
define('MAX_FILE_SIZE', 1073741824);    // 1024M in bytes
define('ALLOW_TYPE', ['jpg', 'jpeg', 'gif', 'png', 'mp4', 'mp3', 'zip', 'apk', 'pdf', 'rar']);


if (isset($_POST['act']) && $_POST['act'] == 'upload') {
    $data = $_POST;

    $arr = uploadRun($data, UPLOAD_PATH . date("Y") . '/' . date('m') . '/');
    echo json_encode($arr);
    exit;
}

$arr = uploadChunked();
echo json_encode($arr);
exit;

// 上传到指定位置
function uploadRun($file, $save_path)
{
    $max_file_size_in_bytes = MAX_FILE_SIZE;

    $result = ['mes' => '', 'code' => 2];

    if (!isset($file)) {
        $result['mes'] = '找不到文件';
        return $result;
    }else if (!isset($file["tmp_name"])) {
        $result['mes'] = '文件无法上传';
        return $result;
    }/* else if(!is_uploaded_file($file["tmp_name"])) {
        // TODO: `is_uploaded_file` 函数对于非 HTTP POST 方法上传的 file 直接返回 false，这里大文件最终是切片整合，不符合 POST上传
        $result['mes'] = '文件不是通过 HTTP POST 上传';
        return $result;
    }*/ else if (!isset($file['name'])) {
        $result['mes'] = '文件名不存在';
        return $result;
    }

    $file_size = $file['size'];
    if (!$file_size || $file_size > $max_file_size_in_bytes) {
        $result['mes'] = '文件size太大';
        return $result;
    }

    if ($file_size <= 0) {
        $result['mes'] = '文件大小不能为0';
        return $result;
    }

    $path_info = pathinfo($file['name']);
    $file_extension = $path_info["extension"];

    // 限制文件类型
    /*if ($file_extension != 'apk') {
        $result['mes'] = '文件类型错误';
        return $result;
    }*/

    $file_name = $save_path . $path_info['filename'] . '.' . $file_extension;

    // TODO: `move_uploaded_file` 函数对于非 HTTP POST 方法上传的 file 直接返回 false，即使文件上传成功
    //if (!move_uploaded_file($file["tmp_name"], $file_name)) {
    if (move_uploaded_file($file["tmp_name"], $file_name)) {
        log_message('ERROR', json_encode($file));
        $result['mes'] = '上传文件失败： ' . json_encode($file);
        return $result;
    }

    $show_name = substr($file_name, strrpos($file_name, '/') + 1);
    $result = [
        'mes' => '文件上传成功',
        'code' => 1,
        'url' => $file_name,
        'size' => $file_size,
        'show_name' => $show_name
    ];
    return $result;
}

function uploadChunked()
{
    //关闭缓存
    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
    header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
    header("Cache-Control: no-store, no-cache, must-revalidate");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");

    $uploader = new FileUpload();

    //用于断点续传，验证指定分块是否已经存在，避免重复上传
    if (isset($_POST['status'])) {
        if ($_POST['status'] == 'chunkCheck') {
            $target = $uploader->path . $_POST['name'] . '/' . $_POST['chunkIndex'];
            if (file_exists($target) && filesize($target) == $_POST['size']) {
                return ['ifExist' => 1];
            }
            return ['ifExist' => 0];

        } elseif ($_POST['status'] == 'md5Check') {
            // 模拟持久层查询
            $dataArr = array(
                'b0201e4d41b2eeefc7d3d355a44c6f5a' => 'auroras.jpg'
            );

            if (isset($dataArr[$_POST['md5']])) {
                return ['ifExist' => 1, 'path' => $dataArr[$_POST['md5']]];
            }
            return ['ifExist' => 0];
        } elseif ($_POST['status'] == 'chunksMerge') {

            if ($path = $uploader->chunksMerge($_POST['name'], $_POST['chunks'], $_POST['ext'])) {
                //todo 把md5签名存入持久层，供未来的秒传验证
                return ['status' => 1, 'path' => $path, 'type' => 1];
            }
            return ['status' => 0, 'chunksMerge' => 0];
        }
    }

    if (($path = $uploader->upload('file', $_POST)) !== false) {
        return ['status' => 1, 'path' => $path, 'type' => 2];
    }
    return ['status' => 0, 'upload' => 0];
}


function log_message($type, $content, $file = '')
{
    $dir = ROOT . '/logs/';

    $file = $file ? $file . '-' . date('Y-m-d') : date('Y-m-d');
    $fileName = $dir . $file . '.log';

    $f = fopen($fileName, 'a');

    $content = "[start]----------------------------------------:\r\n"
        . "[$type]" . date('Y-m-d H:i:s') . "\r\n"
        . $content . "\r\n"
        . "----------------------------------------[end]\r\n\r\n";

    fwrite($f, $content, strlen($content));
    fclose($f);
}