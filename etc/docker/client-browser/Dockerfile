# Defaults also keep direct Docker builds pinned; bin/client passes its same pins explicitly.
ARG NODE_IMAGE=node@sha256:0e0ff40c39bc087845bfb27465a0df4ea419520094bc35842ff83dd8cbe6f9b6
ARG BROWSER_IMAGE=mcr.microsoft.com/playwright@sha256:57b65fdc9ceabe0ef613124c7bbe2babcf9362c4d85e382fe3b03604e84b428a
FROM ${NODE_IMAGE} AS node
FROM ${BROWSER_IMAGE}
# Use the installation/build runtime with the pinned browser and its system libraries.
COPY --from=node /usr/local/ /usr/local/
