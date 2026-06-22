FROM eclipse-temurin:17-jdk
WORKDIR /src

COPY metadata-extractor.jar /src/metadata-extractor.jar
COPY PocReproducer.java /src/PocReproducer.java
COPY poc_input /src/poc_input
COPY run.sh /src/run.sh

RUN javac -cp /src/metadata-extractor.jar /src/PocReproducer.java && chmod +x /src/run.sh

CMD ["/src/run.sh"]
