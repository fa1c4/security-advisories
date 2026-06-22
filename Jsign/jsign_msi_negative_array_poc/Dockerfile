FROM maven:3.9-eclipse-temurin-17 AS build
WORKDIR /app
RUN mvn -q -B dependency:copy -Dartifact=net.jsign:jsign:7.0 -DoutputDirectory=/app/lib
COPY Poc.java /app/Poc.java
COPY crash.bin /app/crash.bin
RUN javac -cp "lib/*" Poc.java

FROM eclipse-temurin:17-jdk
WORKDIR /app
COPY --from=build /app/ /app/
COPY run.sh /app/run.sh
RUN chmod +x /app/run.sh
CMD ["/app/run.sh"]
