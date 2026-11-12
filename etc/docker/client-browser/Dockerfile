# Defaults also keep direct Docker builds pinned; bin/client passes its same pins explicitly.
ARG NODE_IMAGE=node@sha256:55fb5f5a527ea2c5c4a95f963e48ef90aa38c7b346054fdc8ee9957a2b55d205
ARG BROWSER_IMAGE=mcr.microsoft.com/playwright@sha256:57b65fdc9ceabe0ef613124c7bbe2babcf9362c4d85e382fe3b03604e84b428a
FROM ${NODE_IMAGE} AS node
# Git 2.56.1 fixes the November 2026 Git security advisories. The browser base
# carries an older distribution Git; build the verified upstream release on the same
# base as the runtime so its HTTPS helper uses compatible shared libraries.
FROM ${BROWSER_IMAGE} AS git-build
RUN apt-get update \
    && apt-get install --no-install-recommends --yes \
        build-essential ca-certificates curl libcurl4-openssl-dev libexpat1-dev \
        libpcre2-dev libssl-dev perl xz-utils zlib1g-dev \
    && curl --fail --silent --show-error --location \
        https://www.kernel.org/pub/software/scm/git/git-2.56.1.tar.xz --output /tmp/git.tar.xz \
    && echo 'f0b0bf89264b9bd2e1c640363e7529643989da76281600473c50745cd34a607f  /tmp/git.tar.xz' | sha256sum --check --strict \
    && tar --extract --xz --file /tmp/git.tar.xz --directory /tmp \
    && make -C /tmp/git-2.56.1 -j2 prefix=/usr/local \
        NO_RUST=YesPlease NO_GETTEXT=YesPlease NO_TCLTK=YesPlease USE_LIBPCRE2=YesPlease all \
    && make -C /tmp/git-2.56.1 prefix=/usr/local \
        NO_RUST=YesPlease NO_GETTEXT=YesPlease NO_TCLTK=YesPlease USE_LIBPCRE2=YesPlease \
        DESTDIR=/opt/git install

FROM ${BROWSER_IMAGE}
# Use the installation/build runtime with the pinned browser and its system libraries.
COPY --from=node /usr/local/ /usr/local/
# Refresh OS trust and replace the inherited distribution Git during explicit setup.
RUN apt-get update \
    && apt-get purge --yes git git-man \
    && apt-get install --no-install-recommends --yes \
        ca-certificates curl libexpat1 libpcre2-8-0 openssh-client perl zlib1g \
    && rm -rf /var/lib/apt/lists/*

COPY --from=git-build /opt/git/usr/local/ /usr/local/

# Preserve the distro executable path without retaining its affected Git package.
# A package that reintroduces /usr/bin/git makes this layer fail closed.
RUN ln -s /usr/local/bin/git /usr/bin/git \
    && test "$(git --version)" = 'git version 2.56.1' \
    && test "$(/usr/bin/git --version)" = 'git version 2.56.1' \
    && test "$(readlink /usr/bin/git)" = /usr/local/bin/git \
    && test -x "$(git --exec-path)/git-remote-https"
