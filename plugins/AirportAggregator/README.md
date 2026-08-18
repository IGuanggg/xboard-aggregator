# Xboard Airport Aggregator

这个插件把 Xboard 的会员鉴权与 SublinkPro 的机场聚合能力连接起来。

## 工作方式

- 用户仍然使用 Xboard 原有订阅地址。
- 套餐过期、封禁或账号不可用时，Xboard 继续返回原生 `403`。
- 用户权限组存在聚合映射时，插件从内网 SublinkPro 获取对应分享订阅。
- 未配置映射的权限组继续使用 Xboard 原生节点。
- 返回给客户端的 `subscription-userinfo` 使用 Xboard 用户的到期时间和额度。
- Xboard 后台在“节点管理 → 机场管理”中提供机场增删改查，操作通过内网 API 交给 SublinkPro 执行。

> 这属于直接分发模式。套餐到期后订阅链接会失效，但客户端已经保存的上游节点可能继续可用，直到上游机场更换节点凭据或节点本身失效。

## 部署 SublinkPro

在项目根目录执行：

```bash
cp compose.sample.yaml compose.yaml
docker compose -f compose.yaml -f compose.airport.sample.yaml up -d --build
```

SublinkPro 的 `8000` 端口只绑定服务器本机 `127.0.0.1`，不会监听公网地址。远程管理时可以使用 SSH 端口转发：

```bash
ssh -L 8000:127.0.0.1:8000 root@你的服务器
```

然后在本机浏览器打开 `http://127.0.0.1:8000`。

SublinkPro 默认账号为 `admin / 123456`，首次登录后必须立即修改。

## 配置步骤

1. 登录 SublinkPro，立即修改默认密码，然后在 Access Key 页创建一个给 Xboard 使用的 API Key。
2. 在 Xboard 后台安装并启用 `airport_aggregator` 插件。
3. 设置 `SublinkPro 内网地址` 为 `http://sublinkpro:8000`，并填入上一步的 `SublinkPro API Key`。
4. 进入“节点管理 → 机场管理”，在 Xboard 内添加上游机场；添加后可点击卡片上的刷新按钮立即拉取。
5. 首次聚合订阅仍需在 SublinkPro 创建，为它创建分享链接并复制分享 Token。
6. 在插件设置中配置权限组映射，例如：

```json
{
  "1": "ordinary-share-token",
  "2": "premium-share-token"
}
```

这里的数字是 Xboard 用户 `group_id`，通常由套餐绑定的权限组决定。

## 故障策略

默认 `fail_open=false`。SublinkPro 不可用时，已映射权限组会收到 `502`，避免意外回退并暴露 Xboard 原生线路。确认原生节点可以安全回退后，才建议开启 `fail_open`。

## 安全建议

- SublinkPro 仅加入 Xboard 所在 Docker 网络，不直接暴露公网端口。
- SublinkPro API Key 仅保存在 Xboard 插件配置中，只通过内网请求发送。
- 分享 Token 只保存在 Xboard 插件配置中，不记录到错误日志。
- 上游机场订阅地址只保存在 SublinkPro，不进入 Xboard 用户响应。
- 若需要让已到期用户立即无法使用已经下载的节点，需要改为自有入口中转模式。
