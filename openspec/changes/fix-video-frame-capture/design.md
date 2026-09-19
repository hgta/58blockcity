## Context

`mall/shop/products.php` 中视频上传流程：上传成功后在 `#videoUploadArea` 内渲染带 `controls` 的预览 `<video>`（用户拖动/暂停的对象），同时为「截取当前帧为封面」功能维护一个隐藏的 `<video id="videoCaptureEl">`。截帧函数 `captureVideoFrame()` 仅从 `captureEl` 截图，但其 `currentTime` 从未与预览播放器同步，恒为 0，导致截到的总是第一帧。

## Goals / Non-Goals

**Goals:**
- 点击「截取当前帧为封面」时，截取预览播放器当前呈现的画面。

**Non-Goals:**
- 不修改后端 AJAX 上传接口（`ajax_upload_thumbnail`）与表单字段。
- 不涉及编辑模式下「已有视频、未重新上传」时的截帧入口展示逻辑（现状隐藏，保持不变）。
- 不引入额外前端依赖（ffmpeg.js 等）。

## Decisions

1. **直接从预览播放器元素截帧，而非同步两个 video 的 currentTime**
   - 预览播放器（`videoUploadArea.querySelector('video')`）就是用户拖动后暂停的画面，对其执行 `canvas.drawImage(video)` + `toBlob()` 即为当前帧。
   - 备选方案 A：把预览播放器的 `currentTime` 赋给 `captureEl` 并等待 `seeked` 事件——需要异步等待，时序复杂且截帧时机仍可能与预览画面有偏差；备选方案 B：完全删除 `captureEl`——影响面更大（`saveProduct` 中的 `if (videoCaptureEl)` 判断），不必要。
   - 同源约束满足：新上传视频为 `blob:` URL（同源），编辑场景的相对路径视频同源；外链视频（`video_link`）不提供截取入口，无 canvas 污染风险。

2. **保留 `captureEl` 作为回退**
   - 预览播放器被替换/未就绪时回退到 `captureEl`，保持现有降级行为。

3. **就绪校验**
   - 截帧前检查来源元素的 `readyState >= 2` 且 `videoWidth > 0`，否则提示「视频未就绪，请稍后再试」。

## Risks / Trade-offs

- [用户在播放中（非暂停）点击截取] → 截取的是点击瞬间的播放帧，符合「截取当前帧」语义，可接受。
- [极端情况下浏览器 drawImage 视频帧未渲染] → 已有 try/catch 展示「截取失败」提示，不影响表单其他数据。
