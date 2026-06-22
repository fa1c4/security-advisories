# Java: Eclipse Temurin JDK 17 (via maven:3.9-eclipse-temurin-17)
FROM maven:3.9-eclipse-temurin-17
WORKDIR /app
COPY pom.xml .
COPY PoC.java src/main/java/PoC.java
COPY crash.bin .
COPY run.sh .
RUN mvn compile -q
CMD ["./run.sh"]
