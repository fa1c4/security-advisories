#include <stdlib.h>
#include <string.h>
#include "s2j.h"

typedef struct {
    char name[16];
} Hometown;

typedef struct {
    int id;
    int scores[8];
    char name[10];
    double weight;
    Hometown hometown;
} Student;

/* Minimal crashing JSON: {"nAMe":2}
 * cJSON_GetObjectItem() is case-insensitive by default, so "nAMe"
 * matches the struct field "name". The value is numeric, so
 * cJSON->valuestring is NULL. struct2json then passes NULL to strncpy().
 */
static const char json_input[] = "{\"nAMe\":2}";

int main(void) {
    s2j_init(NULL);

    cJSON *root = cJSON_Parse(json_input);
    if (!root) {
        return 1;
    }

    s2j_create_struct_obj(student, Student);
    if (!student) {
        cJSON_Delete(root);
        return 1;
    }

    /* Triggers NULL pointer dereference in S2J_STRUCT_GET_string_ELEMENT. */
    s2j_struct_get_basic_element(student, root, string, name);

    s2j_delete_struct_obj(student);
    cJSON_Delete(root);
    return 0;
}
