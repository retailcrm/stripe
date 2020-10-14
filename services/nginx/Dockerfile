FROM nginx:stable-alpine

COPY conf/ /etc/nginx/

RUN apk update && apk add ca-certificates && rm -rf /var/cache/apk/*
COPY nginx.crt /usr/local/share/ca-certificates/nginx.crt
RUN update-ca-certificates

EXPOSE 80
