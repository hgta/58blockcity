## Why

商城子站「添加/编辑商品」上传视频后，用户拖动进度条到想要的画面，再点击「截取当前帧为封面」按钮时，截到的永远不是用户拖动后暂停的画面，而是视频第一帧，导致封面截取功能与用户预期不符。

根因：截帧函数 `captureVideoFrame()` 从隐藏的 `<video id="videoCaptureEl">` 元素截取，而该隐藏元素的 `currentTime` 从未与用户实际拖动/暂停的预览播放器（`#videoUploadArea` 内渲染的 `<video controls>`）同步，始终停留在 0，因此总是截到第一帧。

## What Changes

- 修复 `mall/shop/products.php` 中的 `captureVideoFrame()`：截帧时优先从用户实际操作的预览播放器（`#videoUploadArea` 内的 `<video>` 元素）取当前帧，而非始终停在 0 秒的隐藏 `#videoCaptureEl`。
- 保留隐藏 `#videoCaptureEl` 作为回退来源（预览播放器不可用/未就绪时），并保证截帧前视频 `readyState` 就绪。
- 不改变后端 AJAX 上传接口（`ajax_upload_thumbnail`）及表单字段（`video_thumbnail` / `video_thumb_path` / `video_thumb_thumb`），属于纯前端行为修复。

## Capabilities

### New Capabilities
- `mall/product-video-cover-capture`: 商城子站商品视频封面截取能力——上传视频后，用户可在预览播放器中拖动到任意画面并截取该画面作为商品封面。

### Modified Capabilities

（无——`openspec/specs/` 目前为空，无既有 spec 需要修改。）

## Impact

- 受影响代码：`mall/shop/products.php`（商品添加/编辑页面，内嵌 JS 的视频预览与截帧逻辑，约 1899-1995 行区域）。
- 不影响后端 PHP 逻辑、数据库结构、其他子站。
- 无新增依赖。
