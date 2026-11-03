#!/usr/bin/env bash
#
# Integration smoke test for Form Quizz FQI3 against a REAL WordPress site (HTTP + WP-CLI).
#
# !! Run it on a disposable test site only !!  It creates a user, seeds quiz levels and questions,
# replaces the plugin options it needs, imports/deletes questions, and enables the REST API.
#
# Requirements: bash, curl, python3, WP-CLI, the plugin activated, the site reachable over HTTP,
# an administrator account.
#
# Usage:
#   FQI3_SMOKE_CONFIRM=yes \
#   WP="wp --path=/var/www/html" WP_URL=http://localhost:8080 ADMIN_USER=admin ADMIN_PASS=secret \
#   tests/integration/smoke.sh
#
set -u
[ "${FQI3_SMOKE_CONFIRM:-}" = "yes" ] || { echo "Refusing to run: set FQI3_SMOKE_CONFIRM=yes (disposable test site only)."; exit 2; }

WP="${WP:-wp}"
B="${WP_URL:-http://localhost:8080}"
ADMIN_USER="${ADMIN_USER:-admin}"
ADMIN_PASS="${ADMIN_PASS:-admin}"
SUB_USER="fqi3_smoke_sub"; SUB_PASS="smoke-Pass-123"
TMP="$(mktemp -d)"; PASS=0; FAIL=0
trap 'rm -rf "$TMP"; $WP user delete "$SUB_USER" --yes >/dev/null 2>&1' EXIT

ok()   { PASS=$((PASS+1)); printf "PASS  %s\n" "$1"; }
bad()  { FAIL=$((FAIL+1)); printf "FAIL  %s  (%s)\n" "$1" "${2:-}"; }
check(){ if [ "$2" = "$3" ]; then ok "$1"; else bad "$1" "expected '$3', got '$2'"; fi; }
login(){ rm -f "$3"; curl -s -c "$3" -b "$3" -o /dev/null "$B/wp-login.php"
         curl -s -c "$3" -b "$3" -o /dev/null -d "log=$1&pwd=$2&wp-submit=Log+In&testcookie=1" -H "Cookie: wordpress_test_cookie=WP%20Cookie%20check" "$B/wp-login.php"; }
count_q(){ $WP eval 'global $wpdb; echo $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fqi3_quizzes");' 2>/dev/null | tail -1; }
nonce_from(){ python3 - "$1" "$2" <<'PY'
import re,sys
h=open(sys.argv[1],errors='ignore').read(); n=re.findall(r'name="_wpnonce" value="([a-z0-9]+)"',h)
print(n[int(sys.argv[2])] if len(n)>int(sys.argv[2]) else '')
PY
}

echo "== Seed"
$WP eval '
update_option("fqi3_quiz_levels", ["fqi3_quiz_levels_name"=>["free_lvl","members_lvl"],"fqi3_quiz_levels_label"=>["Free","Members"],"fqi3_quiz_levels_is_free"=>[1,0]]);
global $wpdb; $t=$wpdb->prefix."fqi3_quizzes"; $wpdb->query("DELETE FROM $t");
foreach(["free_lvl","members_lvl"] as $l) for($i=1;$i<=6;$i++) $wpdb->insert($t,["niveau"=>$l,"q"=>"Question $i $l","q2"=>"","options"=>json_encode(["a","b","c","d"]),"answer"=>1]);
$o=get_option("fqi3_options"); $o["fqi3_free_trials_per_day"]="3"; $o["fqi3_number_questions_per_quiz"]="4"; $o["fqi3_quiz_api"]=1; $o["fqi3_api_rate_ip"]=0; update_option("fqi3_options",$o);
' >/dev/null 2>&1
[ "$(count_q)" = "12" ] || { echo "ABORT: could not seed the questions table (is the plugin activated and its tables created?)"; exit 2; }
$WP user delete "$SUB_USER" --yes >/dev/null 2>&1
$WP user create "$SUB_USER" smoke@example.test --role=subscriber --user_pass="$SUB_PASS" >/dev/null 2>&1
login "$ADMIN_USER" "$ADMIN_PASS" "$TMP/admin"; login "$SUB_USER" "$SUB_PASS" "$TMP/sub"
[ "$($WP eval 'echo get_role("premium_member") ? "yes" : "no";' 2>/dev/null | tail -1)" = "yes" ] || { echo "ABORT: the premium_member role is missing (deactivate and activate the plugin first)."; exit 2; }

echo "== Access to levels and number of questions"
r=$(curl -s "$B/wp-admin/admin-ajax.php?action=get_questions&niveau=members_lvl")
check "visitor cannot open a members-only level" "$(echo "$r" | python3 -c 'import sys,json;print(json.load(sys.stdin).get("success"))')" "False"
n=$(curl -s "$B/wp-admin/admin-ajax.php?action=get_questions&niveau=free_lvl" | python3 -c 'import sys,json;d=json.load(sys.stdin);print(len(d) if isinstance(d,list) else -1)')
check "visitor gets the configured number of questions (4)" "$n" "4"

echo "== Daily limit (3 per day)"
$WP user meta delete "$($WP user get "$SUB_USER" --field=ID)" fqi3_daily_quiz_count >/dev/null 2>&1
okc=0; for i in 1 2 3 4 5; do
  r=$(curl -s -b "$TMP/sub" -c "$TMP/sub" "$B/wp-admin/admin-ajax.php?action=get_questions&niveau=members_lvl")
  [ "$(echo "$r" | python3 -c 'import sys,json;d=json.load(sys.stdin);print(isinstance(d,list))')" = "True" ] && okc=$((okc+1))
done
check "subscriber can start exactly 3 quizzes" "$okc" "3"

echo "== Questions export and delete"
check "export denied to a subscriber (403)" "$(curl -s -b "$TMP/sub" -o /dev/null -w '%{http_code}' "$B/wp-admin/admin-post.php?action=export_questions")" "403"
check "export denied to an admin without nonce (403)" "$(curl -s -b "$TMP/admin" -o /dev/null -w '%{http_code}' -d 'action=export_questions' "$B/wp-admin/admin-post.php")" "403"
curl -s -b "$TMP/admin" "$B/wp-admin/admin.php?page=fqi3-view-quiz-questions" -o "$TMP/view.html"
N=$(nonce_from "$TMP/view.html" 0)
head=$(curl -s -b "$TMP/admin" -d "action=export_questions&_wpnonce=$N" "$B/wp-admin/admin-post.php" | head -1)
check "export works for an admin with a nonce" "${head:0:9}" "ID,Access"
ID=$($WP eval 'global $wpdb; echo $wpdb->get_var("SELECT MAX(id) FROM {$wpdb->prefix}fqi3_quizzes");' 2>/dev/null | tail -1)
check "delete without nonce is refused" "$(curl -s -b "$TMP/admin" -d "action=delete_question&id=$ID" "$B/wp-admin/admin-ajax.php")" "-1"
DN=$(grep -o 'nonce_delete":"[a-z0-9]*' "$TMP/view.html" | head -1 | sed 's/.*"//')
[ -n "$DN" ] || bad "delete nonce is localized on the questions page"
check "delete with nonce works" "$(curl -s -b "$TMP/admin" -d "action=delete_question&nonce=$DN&id=$ID" "$B/wp-admin/admin-ajax.php" | python3 -c 'import sys,json;print(json.load(sys.stdin).get("success"))')" "True"

echo "== Import: invalid files must not lose data"
curl -s -b "$TMP/admin" "$B/wp-admin/admin.php?page=fqi3-import-export" -o "$TMP/impage.html"
EN=$(nonce_from "$TMP/impage.html" 0); IN=$(nonce_from "$TMP/impage.html" 1)
[ -n "$EN" ] && [ -n "$IN" ] || { bad "import/export nonces found on the page (admin login failed?)"; echo "Result: $PASS passed, $((FAIL)) failed"; exit 1; }
curl -s -b "$TMP/admin" -o "$TMP/export.json" -d "action=fqi3_export_data&_wpnonce=$EN&export_types[]=questions&export_types[]=levels&export_types[]=options" "$B/wp-admin/admin-post.php"
python3 - "$TMP" <<'PY'
import json,sys
t=sys.argv[1]; d=json.load(open(t+'/export.json'))
a=json.loads(json.dumps(d)); a['questions'][0].pop('options',None); json.dump(a,open(t+'/bad_options.json','w'))
b=json.loads(json.dumps(d)); b['questions'][1]['answer']=99; json.dump(b,open(t+'/bad_answer.json','w'))
PY
before=$(count_q)
for f in bad_options bad_answer; do
  curl -s -b "$TMP/admin" -o /dev/null -F action=fqi3_import_data -F "_wpnonce=$IN" -F "import_file=@$TMP/$f.json;type=application/json" "$B/wp-admin/admin-post.php"
  check "invalid import ($f) leaves the questions untouched" "$(count_q)" "$before"
done
curl -s -b "$TMP/admin" -o /dev/null -F action=fqi3_import_data -F "_wpnonce=$IN" -F "import_file=@$TMP/export.json;type=application/json" "$B/wp-admin/admin-post.php"
check "valid import keeps the questions" "$(count_q)" "$before"
check "import keeps each level's free flag" "$($WP option get fqi3_quiz_levels --format=json 2>/dev/null | python3 -c 'import sys,json;print(json.load(sys.stdin)["fqi3_quiz_levels_is_free"])')" "[1, 0]"
UP=$($WP eval 'echo wp_upload_dir()["basedir"];' 2>/dev/null | tail -1)
[ -f "$UP/fqi3_backups/.htaccess" ] && [ -f "$UP/fqi3_backups/index.php" ] && ok "backup folder is protected (.htaccess + index.php)" || bad "backup folder protection"
ls "$UP"/fqi3_backups/backup_*_*_*.json >/dev/null 2>&1 && ok "backups have unguessable names" || bad "backup file names"

echo "== REST API token and rate limit"
TOK=$($WP eval '$tm=new \Form_Quizz_FQI3\TokenManager(); $t=$tm->generate_token(); $o=get_option("fqi3_options"); $o["fqi3_quiz_api_token"]=$tm->encrypt_token($t); $o["fqi3_quiz_api_token_exp_mode"]="never"; update_option("fqi3_options",$o); echo $t;' 2>/dev/null | tail -1)
R="$B/index.php?rest_route=/fqi3/v1/ping"
check "valid token accepted" "$(curl -s -o /dev/null -w '%{http_code}' -H "X-API-Token: $TOK" "$R")" "200"
check "invalid token refused" "$(curl -s -o /dev/null -w '%{http_code}' -H 'X-API-Token: nope' "$R")" "401"
$WP eval 'global $wpdb; $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE \"%fqi3_rate%\""); $o=get_option("fqi3_options"); $o["fqi3_api_rate_ip"]=3; $o["fqi3_api_rate_window"]=60; update_option("fqi3_options",$o);' >/dev/null 2>&1
okc=0; for i in 1 2 3 4 5; do [ "$(curl -s -o /dev/null -w '%{http_code}' -H "X-API-Token: $TOK" -H "X-Forwarded-For: 10.0.0.$i" "$R")" = "200" ] && okc=$((okc+1)); done
check "rate limit 3/min: exactly 3 allowed, spoofed X-Forwarded-For ignored" "$okc" "3"
$WP eval 'global $wpdb; $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE \"%fqi3_rate%\""); $o=get_option("fqi3_options"); $o["fqi3_api_rate_ip"]=0; update_option("fqi3_options",$o);' >/dev/null 2>&1

echo "== Lifecycle and cron"
$WP plugin deactivate form-quizz-fqi3 >/dev/null 2>&1
check "premium role survives deactivation" "$($WP eval 'echo get_role("premium_member") ? "yes" : "no";' 2>/dev/null | tail -1)" "yes"
$WP plugin activate form-quizz-fqi3 >/dev/null 2>&1
$WP eval '1;' >/dev/null 2>&1
check "weekly statistics are scheduled" "$($WP eval 'echo wp_next_scheduled("fqi3_weekly_statistics_event") ? "yes" : "no";' 2>/dev/null | tail -1)" "yes"
check "monthly statistics are scheduled" "$($WP eval 'echo wp_next_scheduled("fqi3_monthly_statistics_event") ? "yes" : "no";' 2>/dev/null | tail -1)" "yes"

echo "== Front-end assets are only loaded where needed"
PID=$($WP post create --post_type=page --post_status=publish --post_title="FQI3 smoke quiz" --post_content="[fqi3_quizz_form]" --porcelain 2>/dev/null | tail -1)
PID2=$($WP post create --post_type=page --post_status=publish --post_title="FQI3 smoke plain" --post_content="Nothing here" --porcelain 2>/dev/null | tail -1)
with=$(curl -s "$B/?page_id=$PID" | grep -c 'front-script.js'); without=$(curl -s "$B/?page_id=$PID2" | grep -c 'front-script.js')
$WP post delete "$PID" "$PID2" --force >/dev/null 2>&1
[ "$with" -ge 1 ] && ok "quiz page loads front-script.js" || bad "quiz page loads front-script.js" "$with"
check "plain page does not load front-script.js" "$without" "0"
check "no third-party CDN on the public quiz page" "$(curl -s "$B/?page_id=$PID" | grep -c 'cdn.jsdelivr.net')" "0"

echo
echo "Result: $PASS passed, $FAIL failed"
[ "$FAIL" -eq 0 ]
