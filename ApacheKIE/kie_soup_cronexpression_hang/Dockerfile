FROM maven:3.9-eclipse-temurin-17
WORKDIR /work

COPY pom.xml /work/pom.xml
RUN mvn -q -B dependency:copy-dependencies -DoutputDirectory=/work/libs

COPY crash.bin /tmp/crash.bin
COPY Poc.java /work/Poc.java
COPY run.sh /work/run.sh

RUN javac -cp "/work/libs/*" /work/Poc.java && chmod +x /work/run.sh

CMD ["/work/run.sh"]
