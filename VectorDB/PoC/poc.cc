#include <string>
#include <unordered_map>
#include <vector>

#include "query/expr/expr.hpp"
#include "query/expr/expr_types.hpp"
#include "utils/json.hpp"

/* Minimal crashing filter expression: "2)e+"
 * SplitTokens() accepts the unmatched closing parenthesis.
 * ShuntingYard() later handles ')' by unconditionally popping operator_stack.
 * When the stack is empty, this is invalid container usage / undefined behavior
 * and can terminate the process under sanitizer/hardened builds.
 */
static const char kInput[] = "2)e+";

int main() {
    using FT = vectordb::engine::meta::FieldType;

    std::unordered_map<std::string, FT> field_map{
        {"ID1", FT::INT1},
        {"ID2", FT::INT2},
        {"ID4", FT::INT4},
        {"ID8", FT::INT8},
        {"Score", FT::DOUBLE},
        {"ScoreF", FT::FLOAT},
        {"Flag", FT::BOOL},
        {"Doc", FT::STRING},
        {"Geo", FT::GEO_POINT},
        {"@distance", FT::DOUBLE},
    };

    std::string expression(kInput);
    std::vector<vectordb::query::expr::ExprNodePtr> nodes;
    vectordb::query::expr::Expr expr;

    auto status = expr.ParseNodeFromStr(expression, nodes, field_map, false);

    if (status.ok()) {
        for (auto &node : nodes) {
            vectordb::Json json;
            expr.DumpToJson(node, json);
        }
    }

    return 0;
}
