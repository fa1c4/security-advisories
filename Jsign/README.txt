# Jsign MSI/OLE2 NegativeArraySizeException PoC

This PoC demonstrates an unchecked size/count field issue in Jsign's MSI/OLE2 parsing path.
A malformed MSI/OLE2 input triggers NegativeArraySizeException in the bundled POIFS parser while constructing MSIFile.

Files:
- Dockerfile
- run.sh
- Poc.java
- crash.bin
- docker_build.log
- docker_run.log

Build and run:

  docker build -t jsign-msi-negative-array-poc .
  docker run --rm jsign-msi-negative-array-poc

Expected vulnerable behavior:

  [VULNERABILITY CONFIRMED] NegativeArraySizeException triggered!
  Message: -535703600
  java.lang.NegativeArraySizeException: -535703600
      at net.jsign.poi.poifs.storage.HeaderBlock.getBATArray(HeaderBlock.java:323)
      at net.jsign.poi.poifs.filesystem.POIFSFileSystem.readCoreContents(POIFSFileSystem.java:395)
      at net.jsign.msi.MSIFile.<init>(MSIFile.java:121)

Notes:
- This PoC does not claim memory corruption, code execution, or information disclosure.
- The confirmed impact is availability / unexpected unchecked exception when attacker-controlled MSI files are parsed by Jsign.
- Exact affected version range should be confirmed by maintainers.
