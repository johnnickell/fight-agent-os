# Defaults also keep direct Docker builds pinned; bin/client passes its same pins explicitly.
ARG NODE_IMAGE=node@sha256:55fb5f5a527ea2c5c4a95f963e48ef90aa38c7b346054fdc8ee9957a2b55d205
ARG BROWSER_IMAGE=mcr.microsoft.com/playwright@sha256:57b65fdc9ceabe0ef613124c7bbe2babcf9362c4d85e382fe3b03604e84b428a
FROM ${NODE_IMAGE} AS node
FROM ${BROWSER_IMAGE}
# Use the installation/build runtime with the pinned browser and its system libraries.
COPY --from=node /usr/local/ /usr/local/
# Refresh the browser base's separate OS trust store during explicit setup.
RUN apt-get update \
    && apt-get install --no-install-recommends --yes ca-certificates \
    && rm -rf /var/lib/apt/lists/*
