import com.drew.imaging.ImageMetadataReader;
import com.drew.metadata.Metadata;
import java.io.*;

public class PocReproducer {
    public static void main(String[] args) throws Exception {
        byte[] data;
        try (FileInputStream fis = new FileInputStream("poc_input")) {
            data = fis.readAllBytes();
        }

        System.out.println("PoC size: " + data.length + " bytes");
        System.out.println("First 8 bytes: " + String.format("%02x %02x %02x %02x %02x %02x %02x %02x",
            data[0]&0xff, data[1]&0xff, data[2]&0xff, data[3]&0xff,
            data[4]&0xff, data[5]&0xff, data[6]&0xff, data[7]&0xff));

        /* The input starts with ICO magic (0000 0100) and has imageCount=0xD641 (54,849).
         * The IcoReader loop allocates an IcoDirectory per iteration, causing OOM. */
        System.out.println("Calling ImageMetadataReader.readMetadata()...");
        System.out.println("Expected: OutOfMemoryError due to unbounded loop in IcoReader");

        ByteArrayInputStream input = new ByteArrayInputStream(data);
        Metadata metadata = ImageMetadataReader.readMetadata(input);

        System.out.println("Done (unexpected - no OOM)");
    }
}
