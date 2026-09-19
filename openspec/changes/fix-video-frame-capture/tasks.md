## 1. 截帧逻辑修复

- [x] 1.1 修改 `mall/shop/products.php` 中 `captureVideoFrame()`：优先取 `videoUploadArea` 内的预览播放器（`querySelector('video')`）作为截帧来源，`readyState >= 2` 且 `videoWidth > 0` 才使用；否则回退到隐藏 `#videoCaptureEl`，两者均未就绪时提示「视频未就绪，请稍后再试」并终止。验证：上传视频后拖动到视频中段暂停，点击「截取当前帧为封面」，预览图与暂停画面一致，而非第一帧
- [x] 1.2 保持截取成功后的 AJAX 上传（`ajax_upload_thumbnail`）与表单回填（`video_thumb_path` / `video_thumb_thumb` / `videoThumbPreview`）逻辑不变。验证：截取成功后封面预览更新、成功提示展示，商品保存后封面字段正确落库

## 2. 回归验证

- [x] 2.1 回归验证未拖动直接截取、视频未就绪时点击截取、截取失败提示三种场景，行为符合 `specs/mall/product-video-cover-capture/spec.md` 中各 Scenario
- [x] 2.2 运行 `openspec status --change fix-video-frame-capture` 确认所有任务完成，spec 校验通过（`openspec validate --specs`）
