# Xboard 机场面板（聚合定制版）

基于 [cedar2025/Xboard](https://github.com/cedar2025/Xboard) 的机场面板部署仓库，附加自研 **AirportAggregator 插件**（对接 sublinkpro 聚合分发）。

部署于 HostDare `103.79.118.103`，面板端口 `7001`。

## 定制内容

- `compose.yaml`：本地定制镜像 `xboard-airport-aggregator:20260806-nodeview`（自构建，`pull_policy: never`）
  - 挂载 `.env` / `.docker/.data`（SQLite 数据）/ `plugins` / `storage`，并接入 `sublink-network` 与 sublinkpro 互通
- `plugins/AirportAggregator/`：聚合分发插件
  - `Services/*`：Sublink 管理端 API 客户端、分组路由解析
  - `Controllers/AirportController.php` + `routes/api.php`：聚合订阅对外接口
- `compose.*.sample.yaml`：上游官方模板（1Panel / host / split）

## 部署

```bash
cp .env.example .env        # 设置 APP_KEY / DB 等
docker compose up -d        # 前提: 已构建 xboard-airport-aggregator 镜像
```

## 注意

- 面板后端源码主体在上游 Xboard 镜像内，本仓库只追踪**配置层 + 定制插件**
- `.docker/.data/database.sqlite`（用户/订单数据）为运行时数据，**不入库**
- 面板曾因内存占用（~892M）停用，重新启动后以本仓库 compose 为准