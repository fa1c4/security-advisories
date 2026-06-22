# Javassist ClassFile parser OOM PoC

This PoC demonstrates a resource exhaustion issue in Javassist 3.31.0-GA.
A crafted 54-byte malformed Java class file is passed to ClassPool.makeClass().
The parser attempts to allocate an excessive byte array while reading an AttributeInfo length field and triggers java.lang.OutOfMemoryError.

Files:
- Dockerfile
- run.sh
- Poc.java
- crash.bin
- javassist.jar
- docker_build.log
- docker_run.log

Build and run:

  docker build -t javassist-classfile-oom-poc .
  docker run --rm javassist-classfile-oom-poc

Expected vulnerable behavior:

  Input size: 54 bytes
  [!] OutOfMemoryError triggered: Java heap space
  java.lang.OutOfMemoryError: Java heap space
      at javassist.bytecode.AttributeInfo.<init>(AttributeInfo.java:70)
      at javassist.bytecode.AttributeInfo.read(AttributeInfo.java:142)
      at javassist.bytecode.MethodInfo.read(MethodInfo.java:568)
      at javassist.bytecode.ClassFile.read(ClassFile.java:817)
      at javassist.ClassPool.makeClass(ClassPool.java:707)

Notes:
- This PoC does not claim memory corruption, code execution, or information disclosure.
- The confirmed impact is availability/resource exhaustion when attacker-controlled class files are parsed by Javassist.
- Exact affected version range should be confirmed by maintainers.
