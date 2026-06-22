import java.lang.reflect.Constructor;
import java.lang.reflect.InvocationTargetException;
import java.lang.reflect.Method;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.nio.file.Path;
import java.util.Date;

public class Poc {
  private static final String[] CRON_CLASSES = {
      "org.kie.soup.commons.cron.CronExpression",
      "org.kie.soup.commons.util.CronExpression",
      "org.kie.soup.commons.validation.CronExpression",
      "org.drools.core.time.impl.CronExpression"
  };

  public static void main(String[] args) throws Exception {
    byte[] data = Files.readAllBytes(Path.of("/tmp/crash.bin"));
    String expr = new String(data, StandardCharsets.ISO_8859_1).trim();
    System.out.println("Parsing " + data.length + " byte cron expression");

    Class<?> cronClass = null;
    for (String candidate : CRON_CLASSES) {
      try {
        cronClass = Class.forName(candidate);
        System.out.println("Using " + candidate);
        break;
      } catch (ClassNotFoundException ignored) {
      }
    }
    if (cronClass == null) {
      throw new ClassNotFoundException("No known CronExpression class found");
    }

    try {
      Constructor<?> ctor = cronClass.getConstructor(String.class);
      Object cron = ctor.newInstance(expr);
      for (String methodName : new String[] {"getNextValidTimeAfter", "getTimeAfter"}) {
        try {
          Method method = cronClass.getMethod(methodName, Date.class);
          Object next = method.invoke(cron, new Date(0));
          System.out.println(methodName + " returned " + next);
          return;
        } catch (NoSuchMethodException ignored) {
        }
      }
      System.out.println("CronExpression constructed");
    } catch (InvocationTargetException e) {
      Throwable cause = e.getCause();
      System.out.println("Cron parser threw: " + cause.getClass().getName() + ": " + cause.getMessage());
    }
  }
}
