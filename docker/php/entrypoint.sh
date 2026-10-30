#!/bin/sh
# Trust the sandbox proxy's CA at runtime when it is mounted (empty file elsewhere), then run the command.
set -e
if [ -s /run/ca-bundle.crt ] && [ ! -f /usr/local/share/ca-certificates/runtime-proxy.crt ]; then
	cp /run/ca-bundle.crt /usr/local/share/ca-certificates/runtime-proxy.crt
	update-ca-certificates >/dev/null 2>&1 || true
fi
exec "$@"
