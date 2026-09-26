#!/bin/bash
# Hermes 原生会话 API 探测脚本（admin-ai-training-console task 1.1）
# 用法: bash probe-hermes-sessions.sh <API_SERVER_KEY>
# 跑完把完整输出发回，用于确定 HermesClient 的接口设计

KEY="${1:?用法: bash probe-hermes-sessions.sh <API_SERVER_KEY>}"
BASE="http://127.0.0.1:8642"
AUTH="Authorization: Bearer $KEY"

j() { echo "===== $1 ====="; shift; "$@" 2>&1 | head -c 1500; echo; echo; }

echo "########## 1. 会话列表（GET /api/sessions，验证鉴权与返回结构） ##########"
j "GET /api/sessions" curl -sS -w "\n[HTTP %{http_code}]" "$BASE/api/sessions" -H "$AUTH"

echo "########## 2. 创建会话（空体，看它接受什么/报什么错） ##########"
j "POST /api/sessions {}" curl -sS -w "\n[HTTP %{http_code}]" -X POST "$BASE/api/sessions" -H "$AUTH" -H "Content-Type: application/json" -d '{}'

echo "########## 3. 创建会话（带常见字段猜测：title + system_prompt） ##########"
j "POST sessions {title,system_prompt}" curl -sS -w "\n[HTTP %{http_code}]" -X POST "$BASE/api/sessions" -H "$AUTH" -H "Content-Type: application/json" \
  -d '{"title":"probe-test","system_prompt":"你是测试助手，只回复OK","metadata":{"from":"58tl-probe"}}'

echo "########## 4. 创建会话（带 X-Hermes-Session-Key 隔离头，admin 分区） ##########"
j "POST sessions + Session-Key admin" curl -sS -w "\n[HTTP %{http_code}]" -X POST "$BASE/api/sessions" -H "$AUTH" -H "Content-Type: application/json" -H "X-Hermes-Session-Key: web:58tl:admin" -d '{"title":"probe-admin"}'

echo "########## 5. 带记忆写入指令的对话（chat/completions + Session-Key） ##########"
j "chat + 记忆指令" curl -sS -w "\n[HTTP %{http_code}]" --max-time 60 -X POST "$BASE/v1/chat/completions" -H "$AUTH" -H "Content-Type: application/json" -H "X-Hermes-Session-Key: web:58tl:admin" \
  -d '{"model":"hermes-agent","messages":[{"role":"user","content":"系统指令：请永久记住：58tl平台探测标记PROBE-XYZ-2026。记住后只回复DONE"}]}'

echo "########## 6. 新会话验证记忆是否跨会话生效（不同 Session-Id） ##########"
j "验证记忆" curl -sS -w "\n[HTTP %{http_code}]" --max-time 60 -X POST "$BASE/v1/chat/completions" -H "$AUTH" -H "Content-Type: application/json" -H "X-Hermes-Session-Key: web:58tl:admin" \
  -d '{"model":"hermes-agent","messages":[{"role":"user","content":"58tl平台探测标记是什么？只回答内容本身"}]}'
