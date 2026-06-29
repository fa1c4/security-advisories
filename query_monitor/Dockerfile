FROM php:8.3-cli-alpine
RUN apk add --no-cache curl
WORKDIR /poc
COPY . .
CMD ["sh", "run_poc.sh"]
