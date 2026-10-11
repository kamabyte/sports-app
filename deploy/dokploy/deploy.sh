#!/usr/bin/env bash
# Выкатка в Dokploy с Мака, через API: дождаться образа коммита в GitHub
# Actions, затем на каждой установке — залить compose.yml в Raw-стек,
# поставить IMAGE_TAG=sha-<коммит>, Deploy, дождаться итога и /up.
# GitHub сам до серверов не дотягивается, секретов в Actions нет.
#
#     deploy/dokploy/deploy.sh                       — origin/main на все установки
#     deploy/dokploy/deploy.sh <коммит>              — закрепить / откатить на <коммит>
#     deploy/dokploy/deploy.sh -t work [<коммит>]    — только на одну установку
#
# Установки по очереди, на первой ошибке — стоп.
# Всегда sha-тег, не latest: в Environment стека видно, что развёрнуто,
# а откат — тот же вызов с прежним коммитом.
#
# Установка — файл deploy/dokploy/targets/<имя>.env (в .gitignore):
#     DOKPLOY_URL=http://dokploy.example.lan   # Dokploy напрямую, или
#     DOKPLOY_SSH=root@203.0.113.10            # через ssh-туннель к его localhost:3000
#     DOKPLOY_PORT=3000                        # порт Dokploy на сервере (для туннеля)
#     PROJECT=sports STACK=sports              # проект и стек в Dokploy (по умолчанию)
#     KEYCHAIN=dokploy-api-<имя>               # где в связке ключей API-ключ (по умолчанию)
#     APP_URL=…                                # для проверки /up; по умолчанию — из Environment стека
#
# API-ключ: Dokploy → Settings → Profile → API/CLI → Generate, затем один раз
#     security add-generic-password -U -a "$USER" -s dokploy-api-<имя> -w
set -euo pipefail

HERE="$(cd "$(dirname "$0")" && pwd)"
ROOT="$(cd "${HERE}/../.." && pwd)"
WORKFLOW=image.yml

log() { printf '==> %s\n' "$*"; }
ok()  { printf '  ✓ %s\n' "$*"; }
die() { printf '  ✗ %s\n' "$*" >&2; exit 1; }

only=""
while getopts 't:' opt; do
  case "${opt}" in
    t) only="${OPTARG}" ;;
    *) die "использование: deploy/dokploy/deploy.sh [-t <установка>] [<коммит>]" ;;
  esac
done
shift $((OPTIND - 1))

if [[ -n "${only}" ]]; then
  [[ -f "${HERE}/targets/${only}.env" ]] || die "нет установки ${only}: deploy/dokploy/targets/${only}.env"
  TARGETS=("${only}")
else
  TARGETS=()
  for f in "${HERE}"/targets/*.env; do
    [[ -f "${f}" ]] && TARGETS+=("$(basename "${f}" .env)")
  done
  (( ${#TARGETS[@]} )) || die "нет установок: создайте deploy/dokploy/targets/<имя>.env"
fi

# --- Коммит и образ ------------------------------------------------------------
cd "${ROOT}"
SHA="$(git rev-parse "${1:-HEAD}")"
TAG="sha-${SHA:0:7}"
git fetch -q origin
git branch -r --contains "${SHA}" | grep -q . || die "${SHA:0:7} не запушен"
[[ -n "${1:-}" || "${SHA}" == "$(git rev-parse origin/main)" ]] \
  || die "HEAD (${SHA:0:7}) — не origin/main. Запушьте в main или укажите коммит"

log "Образ ${SHA:0:7} (${TAG})"
# У коммита может быть несколько сборок, concurrency отменяет все, кроме
# последней, — отменённые пропускаем. Сразу после push сборка появляется не мгновенно.
built=""
for _ in $(seq 1 12); do
  RUN="$(gh run list --workflow "${WORKFLOW}" --commit "${SHA}" --limit 10 --json databaseId,conclusion \
    -q '[.[] | select(.conclusion != "cancelled")][0].databaseId // empty')"
  if [[ -n "${RUN}" ]]; then
    gh run watch "${RUN}" --exit-status --interval 10 >/dev/null && { built=1; break; }
    [[ "$(gh run view "${RUN}" --json conclusion -q .conclusion)" == cancelled ]] \
      || die "сборка образа упала: gh run view ${RUN} --log-failed"
  fi
  sleep 5
done
[[ -n "${built}" ]] || die "сборки образа для ${SHA:0:7} нет в Actions"
ok "собран"

# В Raw-стеке — копия deploy/dokploy/compose.yml, источник правды — репозиторий
COMPOSE_FILE="$(mktemp)"; trap 'rm -f "${COMPOSE_FILE}"' EXIT
git show "${SHA}:deploy/dokploy/compose.yml" > "${COMPOSE_FILE}"

# --- Одна установка (в подоболочке: свои переменные, туннель, exit) ----------------
deploy_target() {
  local name=$1
  DOKPLOY_URL="" DOKPLOY_SSH="" DOKPLOY_PORT=3000 PROJECT=sports STACK=sports
  KEYCHAIN="dokploy-api-${name}" APP_URL=""
  # shellcheck source=/dev/null
  source "${HERE}/targets/${name}.env"

  log "Установка ${name}: стек ${PROJECT}/${STACK}"
  KEY="$(security find-generic-password -s "${KEYCHAIN}" -w 2>/dev/null || true)"
  [[ -n "${KEY}" ]] || die "нет API-ключа: security add-generic-password -U -a \"\$USER\" -s ${KEYCHAIN} -w"

  # Dokploy без TLS снаружи не трогаем: ключ идёт по ssh-туннелю
  if [[ -n "${DOKPLOY_SSH}" ]]; then
    local port=$(( 20000 + RANDOM % 20000 ))
    ssh -N -o BatchMode=yes -o ExitOnForwardFailure=yes \
      -L "${port}:127.0.0.1:${DOKPLOY_PORT}" "${DOKPLOY_SSH}" &
    local tunnel=$!
    trap 'kill '"${tunnel}"' 2>/dev/null || true' EXIT
    DOKPLOY_URL="http://127.0.0.1:${port}"
    local up=""
    for _ in $(seq 1 30); do
      kill -0 "${tunnel}" 2>/dev/null || die "ssh-туннель к ${DOKPLOY_SSH} не поднялся"
      curl -s -o /dev/null --max-time 2 "${DOKPLOY_URL}/" && { up=1; break; }
      sleep 0.5
    done
    [[ -n "${up}" ]] || die "Dokploy через туннель к ${DOKPLOY_SSH} не отвечает"
  fi
  [[ -n "${DOKPLOY_URL}" ]] || die "в targets/${name}.env нужен DOKPLOY_URL или DOKPLOY_SSH"

  api() {  # api GET|POST <процедура> [json]
    local method=$1 proc=$2 body=${3:-}
    local args=(-sS --max-time 30 -H "x-api-key: ${KEY}" -H 'Content-Type: application/json'
                -w '\n%{http_code}' -X "${method}")
    [[ -n "${body}" ]] && args+=(-d "${body}")
    local out code
    out="$(curl "${args[@]}" "${DOKPLOY_URL}/api/${proc}")" || die "Dokploy недоступен (${DOKPLOY_URL})"
    code="${out##*$'\n'}"; out="${out%$'\n'*}"
    [[ "${code}" == 2* ]] || die "${proc}: HTTP ${code}: ${out:0:300}"
    printf '%s' "${out}"
  }

  COMPOSE_ID="$(api GET project.all | jq -r --arg p "${PROJECT}" --arg n "${STACK}" \
    '[.[] | select(.name == $p) | .. | objects | select(has("composeId") and .name == $n) | .composeId] | first // empty')"
  [[ -n "${COMPOSE_ID}" ]] || die "стек ${PROJECT}/${STACK} не найден"

  # Environment стека — целиком одной строкой; меняем только IMAGE_TAG
  ENV="$(api GET "compose.one?composeId=${COMPOSE_ID}" | jq -r '.env // ""')"
  PREVIOUS="$(sed -n 's/^IMAGE_TAG=//p' <<<"${ENV}")"
  [[ -n "${APP_URL}" ]] || APP_URL="$(sed -n 's/^APP_URL=//p' <<<"${ENV}" | tr -d "\"'")"
  [[ -n "${APP_URL}" ]] || die "нет APP_URL ни в Environment стека, ни в targets/${name}.env"
  if grep -q '^IMAGE_TAG=' <<<"${ENV}"; then
    ENV="$(sed "s/^IMAGE_TAG=.*/IMAGE_TAG=${TAG}/" <<<"${ENV}")"
  else
    ENV="${ENV:+${ENV}$'\n'}IMAGE_TAG=${TAG}"
  fi
  api POST compose.update "$(jq -n --arg id "${COMPOSE_ID}" --arg env "${ENV}" --rawfile file "${COMPOSE_FILE}" \
    '{composeId: $id, sourceType: "raw", composeFile: $file, env: $env}')" >/dev/null
  ok "compose.yml залит, IMAGE_TAG=${TAG} (был ${PREVIOUS:-не задан})"

  # Deploy ставится в очередь Dokploy — ждём запись о новом деплое и её итог
  latest() { api GET "deployment.allByCompose?composeId=${COMPOSE_ID}" \
    | jq -r 'sort_by(.createdAt) | last // {} | "\(.deploymentId // "") \(.status // "")"'; }
  local before id="" status=""
  before="$(latest | cut -d' ' -f1)"
  api POST compose.deploy "$(jq -n --arg id "${COMPOSE_ID}" '{composeId: $id}')" >/dev/null
  for _ in $(seq 1 120); do
    sleep 5
    read -r id status <<<"$(latest)"
    [[ "${id}" != "${before}" && ( "${status}" == "done" || "${status}" == error ) ]] && break
  done
  [[ "${id}" != "${before}" && "${status}" == "done" ]] \
    || die "деплой: ${status:-нет ответа} — лог в Dokploy → ${PROJECT} → ${STACK} → Deployments"
  ok "стек поднят"

  # web при старте гоняет миграции и прогревает кеши — даём ему подняться
  local code=""
  for _ in $(seq 1 24); do
    code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 5 "${APP_URL}/up" || true)"
    if [[ "${code}" == 200 ]]; then
      ok "${APP_URL} отвечает (${TAG})"
      [[ "${PREVIOUS}" == sha-* ]] && ok "откат: deploy/dokploy/deploy.sh -t ${name} ${PREVIOUS#sha-}"
      return 0
    fi
    sleep 5
  done
  die "${APP_URL}/up → ${code}"
}

done_targets=()
for name in "${TARGETS[@]}"; do
  # set -e внутри: вызов в if/|| его бы отключил
  set +e
  ( set -e; deploy_target "${name}" )
  rc=$?
  set -e
  (( rc == 0 )) || die "${name} не выкачен; уже выкачены: ${done_targets[*]:-нет}"
  done_targets+=("${name}")
done
ok "выкачено ${TAG}: ${done_targets[*]}"
