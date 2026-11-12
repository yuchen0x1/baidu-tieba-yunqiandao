FROM alpine:3.23.4

ENV LANG=zh_CN.UTF-8 \
    TZ=Asia/Shanghai \
    WORKDIR=/var/www \
    PS1="\u@\h:\w \$ "

LABEL org.opencontainers.image.title="百度贴吧云签到" \
      org.opencontainers.image.description="Tieba Cloud Sign" \
      org.opencontainers.image.source="https://github.com/yuchen0x1/baidu-tieba-yunqiandao"

COPY ./ $WORKDIR
RUN apk add --no-cache \
       bash \
       busybox-suid \
       caddy \
       git \
       php84 \
       php84-ctype \
       php84-curl \
       php84-fpm \
       php84-gd \
       php84-mbstring \
       php84-mysqli \
       php84-opcache \
       php84-openssl \
       php84-session \
       php84-zip \
       s6-overlay \
       shadow \
       tzdata \
    && ln -sf /usr/share/zoneinfo/${TZ} /etc/localtime \
    && echo -e "${TZ}" > /etc/timezone \
    && echo -e "max_execution_time = 3600\nupload_max_filesize=128M\npost_max_size=128M\nmemory_limit=1024M\ndate.timezone=${TZ}\nopcache.enable=1\nopcache.memory_consumption=128\nopcache.max_accelerated_files=10000" > /etc/php84/conf.d/99-overrides.ini \
    && echo -e "[global]\nerror_log = /dev/stdout\ndaemonize = no\ninclude=/etc/php84/php-fpm.d/*.conf" > /etc/php84/php-fpm.conf \
    && echo -e "[www]\nuser = caddy\ngroup = caddy\nlisten = 127.0.0.1:9000\nlisten.owner = caddy\nlisten.group = caddy\npm = ondemand\npm.max_children = 75\npm.max_requests = 500\npm.process_idle_timeout = 10s\nclear_env = no\ncatch_workers_output = yes\nchdir = $WORKDIR" > /etc/php84/php-fpm.d/www.conf \
    && cp $WORKDIR/docker/Caddyfile /etc/caddy/Caddyfile \
    && sed -i "s#{\\$WORKDIR}#$WORKDIR#g" /etc/caddy/Caddyfile \
    && caddy fmt --overwrite /etc/caddy/Caddyfile \
    && caddy validate --config /etc/caddy/Caddyfile --adapter caddyfile \
    && rm -rf /var/cache/apk/* /tmp/* \
    && git config --global pull.ff only \
    && mkdir /etc/cont-init.d \
    && mkdir /etc/services.d \
    && sed -i 's/\r$//' $WORKDIR/docker/s6-overlay/etc/cont-init.d/* $WORKDIR/docker/s6-overlay/etc/services.d/*/run \
    && cp -r $WORKDIR/docker/s6-overlay/etc/* /etc/ \
    && chmod +x /etc/cont-init.d/* /etc/services.d/*/run

HEALTHCHECK --interval=30s --timeout=5s --start-period=15s --retries=3 \
  CMD wget -q -O /dev/null 'http://127.0.0.1:8080/healthz.php?mode=live' || exit 1

ENTRYPOINT ["/init"]
