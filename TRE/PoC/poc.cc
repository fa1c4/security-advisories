#include <cstring>
#include <string>

#include <tre/tre.h>

/* Approximate matching trigger extracted from the fuzz case.
 * The resulting pattern/text are intentionally tiny to exercise the small-input
 * approximate matcher path in byte mode.
 */
static const unsigned char approx_input[] = {
    0x0a, 0x41, 0x7e, 0x7e, 0x7e, 0x5b, 0x7a, 0x08
};
static const unsigned int approx_input_len = sizeof(approx_input);

int main() {
    const unsigned char *d = approx_input;
    size_t z = approx_input_len;

    std::string pattern(reinterpret_cast<const char *>(d) + 5, 1);
    std::string text(reinterpret_cast<const char *>(d) + 6, z - 6);

    regex_t preg;
    std::memset(&preg, 0, sizeof(preg));

    if (tre_regncompb(&preg, pattern.data(), pattern.size(), REG_EXTENDED) != 0) {
        return 0;
    }

    regmatch_t pmatch[2];
    regamatch_t match;
    std::memset(&match, 0, sizeof(match));
    match.nmatch = 2;
    match.pmatch = pmatch;

    regaparams_t params;
    tre_regaparams_default(&params);
    params.max_cost = 7;
    params.max_ins = 4;
    params.max_del = 4;
    params.max_subst = 1;
    params.max_err = 9;

    (void)tre_regaexecb(&preg, text.c_str(), &match, params, 0);

    tre_regfree(&preg);
    return 0;
}
