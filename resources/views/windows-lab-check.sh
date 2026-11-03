
#!/usr/bin/env bash
set -uo pipefail

TARGET="${1:-192.168.1.105}"
OUT="windows-lab-check-$(date +%Y%m%d-%H%M%S).txt"

if [[ ! "$TARGET" =~ ^([0-9]{1,3}\.){3}[0-9]{1,3}$ ]]; then
  echo "ERROR: target must be an IPv4 address." >&2
  exit 2
fi

for tool in nc tee; do
  if ! command -v "$tool" >/dev/null 2>&1; then
    echo "ERROR: required command '$tool' was not found." >&2
    exit 127
  fi
done

{
  echo "=== Windows 11 Common Service Port Check ==="
  echo "Target: $TARGET"
  echo "Time: $(date)"
  echo "Scope: common Windows TCP service ports only."
  echo "No login attempts, exploit payloads, or configuration changes are performed."
  echo

  printf '[RUNNING] Ping %s (2 probes)\n' "$TARGET"
  if ping -c 2 -W 1000 "$TARGET" >/dev/null 2>&1; then
    echo "[PING OK] Host replied to ICMP."
  else
    echo "[PING FAILED] No ICMP reply; host may still be online with ping filtered."
  fi
  echo

  for port in 135 139 445 3389 5985 5986; do
    case "$port" in
      135) service="RPC endpoint mapper" ;;
      139) service="NetBIOS session" ;;
      445) service="SMB" ;;
      3389) service="Remote Desktop" ;;
      5985) service="WinRM HTTP" ;;
      5986) service="WinRM HTTPS" ;;
    esac

    printf '[RUNNING] TCP %s (%s)\n' "$port" "$service"
    if nc -z -G 2 "$TARGET" "$port" >/dev/null 2>&1; then
      echo "[OPEN] TCP $port ($service) accepted a connection."
    else
      echo "[CLOSED/FILTERED] TCP $port ($service) did not accept a connection."
    fi
  done

  echo
  echo "Interpretation: a reachable port indicates a listener, not a vulnerability."
} | tee "$OUT"

echo
echo "Report saved to: $OUT"
