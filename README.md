# midjourney-proxy
全功能Midjourney Discord代理，支持Midjourney所有功能，稳定，免费


- [x] 支持 Imagine(画图)
- [x] 支持 Imagine 时支持添加图片垫图
- [x] 支持扩图 Pan ⬅️ ➡️ ⬆️ ⬇️
- [x] 支持扩图 ZoomOut 🔍
- [x] 支持自定义扩图 Custom Zoom 🔍
- [x] 支持局部重绘 Vary (Region) 🖌
- [x] 支持 Make Square
- [x] 支持任务实时进度
- [x] 支持 Blend(图片混合)
- [x] 支持 Describe(图生文)
- [x] 支持账号池
- [x] 支持禁用词设置
- [x] 支持图片cdn替换

# 接口

### /image/imagine 画图

**参数**

```json
{
  "prompt": "a cat",
  "images": [url1, url2, ...], // 可选参数
  "notifyUrl": "https://your-server.com/notify", // 可选参数
}
```

**返回**
```json
{
  "code": 0,
  "msg": "ok",
  "taskId": "1710816049856103374",
  "data": []
}
```

### /image/action 图片操作

**参数**

```json
{
    "taskId": "1710816049856103374",
    "customId": "MJ::JOB::upsample::1::749b4d14-75ec-4f16-8765-b2b9a78125fb"
}
```

**返回**
```json
{
  "code": 0,
  "msg": "ok",
  "taskId": "1710816302060986090",
  "data": []
}
```

### 