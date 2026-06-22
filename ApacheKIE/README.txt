# kie-soup / Drools CronExpression hang PoC

This PoC demonstrates a hang / availability issue in org.kie.soup:kie-soup-commons 7.74.0.Final.
A 78-byte malformed cron expression is passed to org.kie.soup.commons.cron.CronExpression.
The parser does not return within the 10-second timeout.

Build and run:

  docker build -t kie-soup-cronexpression-hang-poc .
  docker run --rm kie-soup-cronexpression-hang-poc

Expected vulnerable behavior:

  [*] Running kie-soup CronExpression hang PoC
  Parsing 78 byte cron expression
  Using org.kie.soup.commons.cron.CronExpression
  [*] exit: 124
  [*] Reproduced: parser did not return before timeout.

Notes:
- The attached Docker evidence proves a timeout / hang (exit 124).
- This package does not claim confirmed memory corruption, code execution, or information disclosure.
- Maintainers should confirm exact affected versions and whether this is security-sensitive under the KIE/Drools threat model.
