#!/bin/sh
CP="target/classes"
for jar in $(find /root/.m2/repository -name "*.jar" -type f); do
    CP="$CP:$jar"
done
exec java -Xmx256m -cp "$CP" PoC crash.bin
