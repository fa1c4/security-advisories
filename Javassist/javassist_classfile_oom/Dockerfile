FROM eclipse-temurin:17-jdk
WORKDIR /poc

COPY Poc.java .
COPY crash.bin .
COPY javassist.jar .
COPY run.sh .

RUN javac -cp javassist.jar Poc.java && chmod +x run.sh

CMD ["./run.sh"]
