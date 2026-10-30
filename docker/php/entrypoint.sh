#!/bin/sh
# Trust the sandbox egress gateway's CA at runtime when it is mounted (empty file elsewhere), then run.
set -e
if [ -s /run/ca-bundle.crt ] && [ ! -f /usr/local/share/ca-certificates/runtime-gateway.crt ]; then
	cp /run/ca-bundle.crt /usr/local/share/ca-certificates/runtime-gateway.crt
	update-ca-certificates >/dev/null 2>&1 || true
fi
exec "$@"
