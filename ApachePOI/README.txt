Apache POI HSLF DoS PoC

Files:
- PoC.java: standalone reproducer
- crash.bin: crafted PPT/HSLF trigger input
- Dockerfile: reproducible Maven/JDK 17 environment
- pom.xml: dependencies; currently uses Apache POI 5.2.5
- run.sh: runs PoC with -Xmx256m
- docker_build.log: captured build log
- docker_run.log: captured run log showing OutOfMemoryError

Build and run:

docker build -t apache-poi-hslf-poc .
docker run --rm apache-poi-hslf-poc

Expected vulnerable behavior:
The process terminates with java.lang.OutOfMemoryError: Java heap space in the EscherMetafileBlip.fillFields() -> IOUtils.safelyClone() -> Arrays.copyOfRange() stack.
