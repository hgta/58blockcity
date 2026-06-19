# 设计文档

## 处理流程

```
POST 提交:

  if 有 main_image 文件 → uploadImage() → mainImage + thumb
  
  else if 有 product_video 文件:
      if $_POST['video_thumbnail'] 有 base64 数据:
          decodeBase64ToImage(base64) → GD缩放 → mainImage + thumb
      else:
          $error = '请上传主图或从视频中截取封面'
  
  else:
      $error = '请上传商品主图'
```

## 前端 Canvas 截图

```javascript
// 视频文件选择后
videoInput.onchange = function(e) {
    const file = e.target.files[0];
    const url = URL.createObjectURL(file);
    // 创建隐藏 video 元素播放
    video.src = url;
    video.onloadedmetadata = function() {
        video.currentTime = 1; // 默认取第1秒
    };
    video.onseeked = function() {
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0);
        const dataUrl = canvas.toDataURL('image/jpeg', 0.9);
        document.getElementById('video_thumbnail').value = dataUrl;
        // 更新主图预览
        showMainImagePreview(dataUrl);
    };
};
```

## Base64 解码保存

```php
function decodeBase64ToImage($base64, $maxWidth = 800) {
    $data = explode(',', $base64);
    $img = imagecreatefromstring(base64_decode($data[1] ?? $data[0]));
    if (!$img) return ['success' => false];
    
    // GD 缩放
    $ratio = min($maxWidth / imagesx($img), 1.0);
    $newW = round(imagesx($img) * $ratio);
    $newH = round(imagesy($img) * $ratio);
    $dst = imagecreatetruecolor($newW, $newH);
    imagecopyresampled($dst, $img, 0, 0, 0, 0, $newW, $newH, imagesx($img), imagesy($img));
    imagedestroy($img);
    
    $name = uniqid() . '_' . time() . '.jpg';
    $path = "/assets/uploads/products/" . date('Ym') . '/' . $name;
    imagejpeg($dst, $fullPath, 85);
    imagedestroy($dst);
    
    return ['success' => true, 'file_path' => $relativePath, 'thumb_path' => $thumbPath];
}
```

## 错误处理

| 场景 | 提示 |
|------|------|
| 无主图+无视频 | `请上传商品主图` |
| 无主图+有视频+未截封面 | `请上传主图或从视频中截取封面` |
| Canvas 截图失败 | `视频封面截取失败，请手动上传主图` |
| base64 解码失败 | `封面图片生成失败，请手动上传主图` |
