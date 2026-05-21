<?php defined('BASEPATH') or exit('No direct script access allowed');

#[\AllowDynamicProperties]
class Admin extends MY_Controller
{
    private $themePageSeoStore = APPPATH . 'cache/theme_page_seo.json';
    private $seoExtendedStore = APPPATH . 'cache/seo_extended_settings.json';

    public function __construct()
    {
        parent::__construct();
        
        // Security: Redirect to admin login if not logged in
        // Allow access to the 'login' method itself
        $method = $this->router->fetch_method();
        if ($method !== 'login' && $method !== 'logout' && $method !== 'register') {
            if (!$this->loggedIn || (!$this->Owner && !$this->Admin)) {
                $this->session->set_flashdata('error', 'Please login to access the admin portal.');
                redirect('admin/login');
            }
        }

        // Load necessary models for CMS
        $this->load->model('site');
        $this->load->model('webshop_model');
        $this->load->model('webshop_settings_model');
        $this->load->library('form_validation');
        
        // Data for theme
        $this->data['today_leads'] = $this->db->where('DATE(created_at)', date('Y-m-d'))->count_all_results('leads');
        $this->data['total_products'] = $this->db->count_all_results('products');
    }

    public function index()
    {
        redirect('admin/dashboard');
    }

    public function login()
    {
        if ($this->loggedIn && ($this->Owner || $this->Admin)) {
            redirect('admin/dashboard');
        }

        if ($this->input->post()) {
            $this->form_validation->set_rules('identity', 'Email/Username', 'required');
            $this->form_validation->set_rules('password', 'Password', 'required');

            if ($this->form_validation->run() == true) {
                $identity = $this->input->post('identity');
                $password = $this->input->post('password');
                $remember = (bool)$this->input->post('remember');

                // Try authenticating through SMA/Ion Auth (standard for POS/Admin)
                // Note: Ion_Auth library in this project seems to proxy to auth_model
                // We'll try a manual check if standard fails to be safe for this specific environment
                
                // Reverted to 'sma_users' to match MY_Controller patterns
                $user = $this->db->group_start()
                            ->where('email', $identity)
                            ->or_where('username', $identity)
                            ->or_where('phone', $identity)
                        ->group_end()
                        ->get('sma_users')->row();

                if ($user) {
                    $authenticated = false;
                    
                    // Verify using modern Bcrypt
                    if (password_verify($password, $user->password)) {
                        $authenticated = true;
                    } 
                    // Fallback to legacy MD5 if necessary (common in this project's webshop)
                    elseif ($user->password === md5($password)) {
                        $authenticated = true;
                    }

                    if ($authenticated) {
                        // Check group access
                        // site->getUserGroup usually handles prefix internally if it uses Site model
                        $group = $this->site->getUserGroup($user->id);
                        if ($group && (strtolower($group->name) === 'admin' || strtolower($group->name) === 'owner')) {
                            
                            $session_data = array(
                                'identity' => $user->email,
                                'username' => $user->username,
                                'email'    => $user->email,
                                'user_id'  => $user->id,
                                'id'       => $user->id,
                                'group_id' => $user->group_id,
                                'loggedIn' => true
                            );
                            $this->session->set_userdata($session_data);
                            
                            $this->session->set_flashdata('message', 'Welcome back, Admin!');
                            redirect('admin/dashboard');
                        } else {
                            $this->session->set_flashdata('error', 'Access denied: You do not have administrative privileges.');
                        }
                    } else {
                        $this->session->set_flashdata('error', 'Invalid login credentials.');
                    }
                } else {
                    $this->session->set_flashdata('error', 'User not found.');
                }
            }
        }

        $this->load->view($this->theme . 'admin/login', $this->data);
    }

    public function register()
    {
        if ($this->loggedIn && ($this->Owner || $this->Admin)) {
            redirect('admin/dashboard');
        }

        if ($this->input->post()) {
            $this->form_validation->set_rules('first_name', 'First Name', 'required');
            $this->form_validation->set_rules('last_name', 'Last Name', 'required');
            $this->form_validation->set_rules('username', 'Username', 'required|is_unique[sma_users.username]');
            $this->form_validation->set_rules('email', 'Email', 'required|valid_email|is_unique[sma_users.email]');
            $this->form_validation->set_rules('phone', 'Phone', 'required');
            $this->form_validation->set_rules('password', 'Password', 'required|min_length[8]');
            $this->form_validation->set_rules('password_confirm', 'Confirm Password', 'required|matches[password]');

            if ($this->form_validation->run() == true) {
                $data = [
                    'first_name' => $this->input->post('first_name'),
                    'last_name'  => $this->input->post('last_name'),
                    'username'   => $this->input->post('username'),
                    'email'      => $this->input->post('email'),
                    'phone'      => $this->input->post('phone'),
                    // Temporarily using MD5 for registration compatibility with legacy column lengths (e.g. 32 chars)
                    // The login method already has a Bcrypt/MD5 fallback check.
                    'password'   => md5($this->input->post('password')),
                    'group_id'   => 1, // Full Administrator/Owner access
                    'active'     => 1,
                    'created_on' => time(),
                ];

                if ($this->db->insert('sma_users', $data)) {
                    $this->session->set_flashdata('message', 'Registration successful! You may now login.');
                    redirect('admin/login');
                } else {
                    $this->session->set_flashdata('error', 'Registration failed. Please try again.');
                }
            } else {
                $this->session->set_flashdata('error', validation_errors());
            }
        }

        $this->load->view($this->theme . 'admin/register', $this->data);
    }

    public function logout()
    {
        $this->session->sess_destroy();
        redirect('admin/login');
    }

    public function dashboard()
    {
        $this->data['seo_health'] = $this->get_seo_health_metrics();
        $this->load->view($this->theme . 'admin/header', $this->data);
        $this->load->view($this->theme . 'admin/dashboard', $this->data);
        $this->load->view($this->theme . 'admin/footer');
    }

    public function seo_health_details()
    {
        $type = trim((string)$this->input->get('type', true));
        $allowedTypes = ['indexed_pages', 'active_categories', 'active_products', 'excluded_inactive_pages'];
        if (!in_array($type, $allowedTypes, true)) {
            $type = 'indexed_pages';
        }

        $theme = $this->db->select('webshop_theme')->get('webshop_settings')->row();
        $themeFolder = $this->get_active_theme_folder($theme ? $theme->webshop_theme : 'default');
        $prefix = ($themeFolder ? $themeFolder : 'default') . '/';
        $reserved = ['index', 'about_us', 'contact_us', 'terms_and_conditions', 'privacy_policy', 'login', 'register', 'cart', 'checkout'];

        $this->data['seo_detail_type'] = $type;
        $this->data['seo_detail_rows'] = [];
        $this->data['seo_detail_title'] = 'Indexed Pages Details';
        $this->data['seo_detail_columns'] = ['Source', 'Page', 'URL/Key', 'Status'];

        if ($type === 'indexed_pages') {
            $rows = [
                ['source' => 'Core', 'name' => 'Home', 'value' => 'webshop', 'status' => 'Indexed'],
                ['source' => 'Core', 'name' => 'About Us', 'value' => 'webshop/about_us', 'status' => 'Indexed'],
                ['source' => 'Core', 'name' => 'Contact Us', 'value' => 'webshop/contact_us', 'status' => 'Indexed'],
            ];

            $customPages = $this->webshop_model->getCustomPages();
            if (is_array($customPages)) {
                foreach ($customPages as $sectionPages) {
                    if (!is_array($sectionPages)) {
                        continue;
                    }
                    foreach ($sectionPages as $page) {
                        $pageKey = isset($page['page_key']) ? trim((string)$page['page_key']) : '';
                        $isActive = isset($page['is_active']) ? (int)$page['is_active'] : 0;
                        if ($pageKey === '' || $isActive !== 1) {
                            continue;
                        }
                        $rows[] = [
                            'source' => 'Custom DB',
                            'name' => isset($page['title']) && trim((string)$page['title']) !== '' ? (string)$page['title'] : ucwords(str_replace('_', ' ', $pageKey)),
                            'value' => 'webshop/' . $pageKey,
                            'status' => 'Indexed',
                        ];
                    }
                }
            }

            foreach ($this->get_theme_page_seo_store() as $seoKey => $seoData) {
                if (!is_array($seoData) || strpos($seoKey, $prefix) !== 0) {
                    continue;
                }
                $isActive = isset($seoData['is_active']) ? (int)$seoData['is_active'] : 1;
                if ($isActive !== 1) {
                    continue;
                }
                $file = substr($seoKey, strlen($prefix));
                $slug = preg_replace('/\.php$/i', '', $file);
                if ($slug === '' || in_array($slug, $reserved, true)) {
                    continue;
                }

                $rows[] = [
                    'source' => 'Theme SEO JSON',
                    'name' => ucwords(str_replace('_', ' ', $slug)),
                    'value' => 'webshop/' . $slug,
                    'status' => 'Indexed',
                ];
            }

            $this->data['seo_detail_rows'] = $rows;
        } elseif ($type === 'active_categories') {
            $this->data['seo_detail_title'] = 'Active Categories Details';
            $this->data['seo_detail_columns'] = ['ID', 'Category', 'Reference', 'Status'];

            $categoryValueField = $this->db->field_exists('slug', 'categories') ? 'slug' : 'code';
            if (!$this->db->field_exists($categoryValueField, 'categories')) {
                $categoryValueField = 'id';
            }

            $categories = $this->db
                ->select('id, name, ' . $categoryValueField)
                ->where('is_active', 1)
                ->where('in_eshop', 1)
                ->order_by('id', 'DESC')
                ->get('categories')
                ->result_array();

            foreach ($categories as $category) {
                $this->data['seo_detail_rows'][] = [
                    'id' => isset($category['id']) ? (int)$category['id'] : 0,
                    'name' => isset($category['name']) ? (string)$category['name'] : '',
                    'value' => isset($category[$categoryValueField]) ? (string)$category[$categoryValueField] : '',
                    'status' => 'Active',
                ];
            }
        } elseif ($type === 'active_products') {
            $this->data['seo_detail_title'] = 'Active Products Details';
            $this->data['seo_detail_columns'] = ['ID', 'Product', 'Reference', 'Status'];

            $productValueField = $this->db->field_exists('slug', 'products') ? 'slug' : 'code';
            if (!$this->db->field_exists($productValueField, 'products')) {
                $productValueField = 'id';
            }

            $products = $this->db
                ->select('id, name, ' . $productValueField)
                ->where('is_active', 1)
                ->where('in_eshop', 1)
                ->order_by('id', 'DESC')
                ->get('products')
                ->result_array();

            foreach ($products as $product) {
                $this->data['seo_detail_rows'][] = [
                    'id' => isset($product['id']) ? (int)$product['id'] : 0,
                    'name' => isset($product['name']) ? (string)$product['name'] : '',
                    'value' => isset($product[$productValueField]) ? (string)$product[$productValueField] : '',
                    'status' => 'Active',
                ];
            }
        } else {
            $this->data['seo_detail_title'] = 'Excluded (Inactive) Details';
            $this->data['seo_detail_columns'] = ['Source', 'Page', 'URL/Key', 'Status'];

            $inactiveDbPages = $this->db
                ->where('is_active', 0)
                ->get('webshop_static_pages')
                ->result_array();
            foreach ($inactiveDbPages as $page) {
                $pageKey = isset($page['page_key']) ? (string)$page['page_key'] : '';
                $this->data['seo_detail_rows'][] = [
                    'source' => 'Custom DB',
                    'name' => isset($page['title']) && trim((string)$page['title']) !== '' ? (string)$page['title'] : ucwords(str_replace('_', ' ', $pageKey)),
                    'value' => $pageKey !== '' ? 'webshop/' . $pageKey : '-',
                    'status' => 'Excluded',
                ];
            }

            foreach ($this->get_theme_page_seo_store() as $seoKey => $seoData) {
                if (!is_array($seoData) || strpos($seoKey, $prefix) !== 0) {
                    continue;
                }
                $isActive = isset($seoData['is_active']) ? (int)$seoData['is_active'] : 1;
                if ($isActive !== 0) {
                    continue;
                }

                $file = substr($seoKey, strlen($prefix));
                $slug = preg_replace('/\.php$/i', '', $file);
                if ($slug === '') {
                    continue;
                }
                $this->data['seo_detail_rows'][] = [
                    'source' => 'Theme SEO JSON',
                    'name' => ucwords(str_replace('_', ' ', $slug)),
                    'value' => 'webshop/' . $slug,
                    'status' => 'Excluded',
                ];
            }
        }

        $this->data['seo_detail_count'] = count($this->data['seo_detail_rows']);

        $this->load->view($this->theme . 'admin/header', $this->data);
        $this->load->view($this->theme . 'admin/seo_health_details', $this->data);
        $this->load->view($this->theme . 'admin/footer');
    }

    public function products()
    {
        // Handle deletion if requested
        if ($this->input->get('delete')) {
            $id = (int)$this->input->get('delete');
            $this->db->delete('products', ['id' => $id]);
            redirect('admin/products?deleted=1');
        }

        $this->data['products'] = $this->db->get('products')->result_array();
        
        $this->load->view($this->theme . 'admin/header', $this->data);
        $this->load->view($this->theme . 'admin/products', $this->data);
        $this->load->view($this->theme . 'admin/footer');
    }

    public function product_edit($id = null)
    {
        if ($this->input->post()) {
            $update_data = [
                'name' => $this->input->post('name'),
                'slug' => $this->input->post('slug') ?: url_title($this->input->post('name'), '-', TRUE),
                'description' => $this->input->post('description'),
                'category' => $this->input->post('category'),
                'image_url' => $this->input->post('image_url')
            ];

            if ($id) {
                $this->db->update('products', $update_data, ['id' => $id]);
            } else {
                $this->db->insert('products', $update_data);
            }
            redirect('admin/products?saved=1');
        }

        $this->data['id'] = $id;
        $this->data['product'] = $id ? $this->db->get_where('products', ['id' => $id])->row_array() : [];

        $this->load->view($this->theme . 'admin/header', $this->data);
        $this->load->view($this->theme . 'admin/product_edit', $this->data);
        $this->load->view($this->theme . 'admin/footer');
    }

    // Skeleton methods for other CMS features
    public function leads()
    {
        $this->data['leads'] = $this->db->order_by('id', 'DESC')->get('leads')->result_array();
        
        $this->load->view($this->theme . 'admin/header', $this->data);
        $this->load->view($this->theme . 'admin/leads', $this->data);
        $this->load->view($this->theme . 'admin/footer');
    }
    public function pages()
    {
        $this->data['pages'] = $this->webshop_settings_model->getCustomPages();
        
        // Get active theme from webshop_settings
        $ws = $this->db->get('webshop_settings')->row();
        $theme = $ws ? $ws->webshop_theme : 'default';
        $this->data['active_theme'] = $theme;
        
        // Map theme identifier to folder name based on frontend logic
        $theme_folder = $this->get_active_theme_folder($theme);
        
        // Scan the directory for view files
        $theme_pages = [];
        $dir_path = FCPATH . 'themes/default/views/webshop/' . ($theme_folder ? $theme_folder . '/' : '');
        
        if (is_dir($dir_path)) {
            $files = scandir($dir_path);
            foreach ($files as $file) {
                if (is_file($dir_path . $file) && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                    $seoKey = ($theme_folder ? $theme_folder : 'default') . '/' . $file;
                    $seoData = $this->get_theme_page_seo($seoKey);
                    $theme_pages[] = [
                        'filename' => $file,
                        'name' => ucfirst(str_replace('_', ' ', basename($file, '.php'))),
                        'path' => 'webshop/' . ($theme_folder ? $theme_folder . '/' : '') . $file,
                        'is_active' => isset($seoData['is_active']) ? (int)$seoData['is_active'] : 1,
                        'meta_title' => isset($seoData['meta_title']) ? (string)$seoData['meta_title'] : '',
                        'meta_keywords' => isset($seoData['meta_keywords']) ? (string)$seoData['meta_keywords'] : '',
                        'meta_description' => isset($seoData['meta_description']) ? (string)$seoData['meta_description'] : '',
                    ];
                }
            }
        }
        
        $this->data['theme_pages'] = $theme_pages;
        $this->data['theme_folder_name'] = $theme_folder ? $theme_folder : 'Default Theme (Root)';
        
        $this->load->view($this->theme . 'admin/header', $this->data);
        $this->load->view($this->theme . 'admin/pages', $this->data);
        $this->load->view($this->theme . 'admin/footer');
    }

    public function seo_template()
    {
        $this->load->view($this->theme . 'admin/header', $this->data);
        $this->load->view($this->theme . 'admin/seo_template', $this->data);
        $this->load->view($this->theme . 'admin/footer');
    }

    public function bulk_update_theme_seo()
    {
        if (!$this->input->post('bulk_save_seo')) {
            redirect('admin/pages');
        }

        $ws = $this->db->get('webshop_settings')->row();
        $theme = $ws ? $ws->webshop_theme : 'default';
        $themeFolder = $this->get_active_theme_folder($theme);
        $dirPath = FCPATH . 'themes/default/views/webshop/' . ($themeFolder ? $themeFolder . '/' : '');
        if (!is_dir($dirPath)) {
            $this->session->set_flashdata('error', 'Theme folder not found for bulk SEO update.');
            redirect('admin/pages');
        }

        $titleTemplate = trim((string)$this->input->post('template_meta_title', true));
        $keywordsTemplate = trim((string)$this->input->post('template_meta_keywords', true));
        $descriptionTemplate = trim((string)$this->input->post('template_meta_description', true));
        $onlyEmpty = (int)$this->input->post('only_empty') === 1;

        if ($titleTemplate === '' && $keywordsTemplate === '' && $descriptionTemplate === '') {
            $this->session->set_flashdata('error', 'Please enter at least one SEO template field.');
            redirect('admin/pages');
        }

        $files = scandir($dirPath);
        foreach ($files as $file) {
            if (!is_file($dirPath . $file) || pathinfo($file, PATHINFO_EXTENSION) !== 'php') {
                continue;
            }

            $pageName = ucwords(str_replace('_', ' ', basename($file, '.php')));
            $seoKey = ($themeFolder ? $themeFolder : 'default') . '/' . $file;
            $existingSeo = $this->get_theme_page_seo($seoKey);

            $currentTitle = isset($existingSeo['meta_title']) ? (string)$existingSeo['meta_title'] : '';
            $currentKeywords = isset($existingSeo['meta_keywords']) ? (string)$existingSeo['meta_keywords'] : '';
            $currentDescription = isset($existingSeo['meta_description']) ? (string)$existingSeo['meta_description'] : '';

            $newTitle = str_replace('{page}', $pageName, $titleTemplate);
            $newKeywords = str_replace('{page}', $pageName, $keywordsTemplate);
            $newDescription = str_replace('{page}', $pageName, $descriptionTemplate);

            $this->save_theme_page_seo($seoKey, [
                'meta_title' => $onlyEmpty && $currentTitle !== '' ? $currentTitle : trim((string)$newTitle),
                'meta_keywords' => $onlyEmpty && $currentKeywords !== '' ? $currentKeywords : trim((string)$newKeywords),
                'meta_description' => $onlyEmpty && $currentDescription !== '' ? $currentDescription : trim((string)$newDescription),
                'meta_robots' => isset($existingSeo['meta_robots']) ? (string)$existingSeo['meta_robots'] : '',
                'meta_tags' => isset($existingSeo['meta_tags']) ? (string)$existingSeo['meta_tags'] : '',
                'page_placement' => isset($existingSeo['page_placement']) ? (string)$existingSeo['page_placement'] : 'none',
                'is_active' => isset($existingSeo['is_active']) ? (int)$existingSeo['is_active'] : 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $this->session->set_flashdata('message', 'SEO template applied to all theme pages successfully.');
        redirect('admin/pages');
    }

    public function bulk_delete_theme_seo()
    {
        if (!$this->input->post('bulk_delete_seo')) {
            redirect('admin/seo_template');
        }

        $ws = $this->db->get('webshop_settings')->row();
        $theme = $ws ? $ws->webshop_theme : 'default';
        $themeFolder = $this->get_active_theme_folder($theme);
        $dirPath = FCPATH . 'themes/default/views/webshop/' . ($themeFolder ? $themeFolder . '/' : '');
        if (!is_dir($dirPath)) {
            $this->session->set_flashdata('error', 'Theme folder not found for SEO template delete.');
            redirect('admin/seo_template');
        }

        $files = scandir($dirPath);
        foreach ($files as $file) {
            if (!is_file($dirPath . $file) || pathinfo($file, PATHINFO_EXTENSION) !== 'php') {
                continue;
            }

            $seoKey = ($themeFolder ? $themeFolder : 'default') . '/' . $file;
            $existingSeo = $this->get_theme_page_seo($seoKey);

            $this->save_theme_page_seo($seoKey, [
                'meta_title' => '',
                'meta_keywords' => '',
                'meta_description' => '',
                'meta_robots' => '',
                'meta_tags' => isset($existingSeo['meta_tags']) ? (string)$existingSeo['meta_tags'] : '',
                'page_placement' => isset($existingSeo['page_placement']) ? (string)$existingSeo['page_placement'] : 'none',
                'is_active' => isset($existingSeo['is_active']) ? (int)$existingSeo['is_active'] : 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $this->session->set_flashdata('message', 'SEO template fields were deleted for all theme pages.');
        redirect('admin/seo_template');
    }

    public function bulk_purge_theme_seo()
    {
        if (!$this->input->post('bulk_purge_seo')) {
            redirect('admin/seo_template');
        }

        $ws = $this->db->get('webshop_settings')->row();
        $theme = $ws ? $ws->webshop_theme : 'default';
        $themeFolder = $this->get_active_theme_folder($theme);
        $dirPath = FCPATH . 'themes/default/views/webshop/' . ($themeFolder ? $themeFolder . '/' : '');
        if (!is_dir($dirPath)) {
            $this->session->set_flashdata('error', 'Theme folder not found for SEO hard delete.');
            redirect('admin/seo_template');
        }

        $prefix = ($themeFolder ? $themeFolder : 'default') . '/';
        $this->delete_theme_page_seo_keys_by_prefix($prefix);

        $this->session->set_flashdata('message', 'SEO entries were permanently removed for current theme pages.');
        redirect('admin/seo_template');
    }

    public function bulk_remove_seo_data()
    {
        $selected_pages = $this->input->post('selected_pages');
        
        if (empty($selected_pages) || !is_array($selected_pages)) {
            $this->session->set_flashdata('error', 'No pages were selected.');
            redirect('admin/pages');
        }

        $ws = $this->db->get('webshop_settings')->row();
        $theme = $ws ? $ws->webshop_theme : 'default';
        $themeFolder = $this->get_active_theme_folder($theme);
        $prefix = ($themeFolder ? $themeFolder : 'default') . '/';

        $updated_count = 0;
        foreach ($selected_pages as $file) {
            $seoKey = $prefix . basename($file);
            $existingSeo = $this->get_theme_page_seo($seoKey);

            $this->save_theme_page_seo($seoKey, [
                'meta_title'       => '',
                'meta_keywords'    => '',
                'meta_description' => '',
                'meta_robots'      => '',
                'meta_tags'        => '',
                'page_placement'   => isset($existingSeo['page_placement']) ? $existingSeo['page_placement'] : 'none',
                'is_active'        => isset($existingSeo['is_active']) ? (int)$existingSeo['is_active'] : 1,
                'updated_at'       => date('Y-m-d H:i:s'),
            ]);
            $updated_count++;
        }

        $this->session->set_flashdata('message', "Successfully removed SEO data for {$updated_count} page(s).");
        redirect('admin/pages');
    }

    public function create_theme_page()
    {
        if (!$this->input->post('create_theme_page')) {
            redirect('admin/pages');
        }

        $pageName = trim((string)$this->input->post('page_name', true));
        $pageSlug = trim((string)$this->input->post('page_slug', true));
        $pageTitle = trim((string)$this->input->post('page_title', true));
        $pagePlacement = trim((string)$this->input->post('page_placement', true));
        $isActive = (int)$this->input->post('is_active');

        if ($pageName === '') {
            $this->session->set_flashdata('error', 'Page name is required.');
            redirect('admin/pages');
        }

        if ($pageSlug === '') {
            $pageSlug = url_title($pageName, '_', true);
        } else {
            $pageSlug = url_title($pageSlug, '_', true);
        }

        if ($pageSlug === '') {
            $this->session->set_flashdata('error', 'Invalid page slug.');
            redirect('admin/pages');
        }

        $theme = $this->db->get('webshop_settings')->row();
        $themeFolder = $this->get_active_theme_folder($theme ? $theme->webshop_theme : 'default');
        $dirPath = FCPATH . 'themes/default/views/webshop/' . ($themeFolder ? $themeFolder . '/' : '');

        if (!is_dir($dirPath)) {
            $this->session->set_flashdata('error', 'Selected theme folder not found.');
            redirect('admin/pages');
        }

        $filename = $pageSlug . '.php';
        $filePath = $dirPath . $filename;
        if (file_exists($filePath)) {
            $this->session->set_flashdata('error', 'Page already exists for selected theme.');
            redirect('admin/pages');
        }

        $safeTitle = $pageTitle !== '' ? $pageTitle : ucwords(str_replace('_', ' ', $pageSlug));
        $template = "<!DOCTYPE html>\n<html>\n<head>\n    <meta charset=\"utf-8\">\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n    <title>" . htmlspecialchars($safeTitle, ENT_QUOTES, 'UTF-8') . "</title>\n</head>\n<body>\n    <div class=\"container\" style=\"padding: 40px 15px;\">\n        <h1>" . htmlspecialchars($safeTitle, ENT_QUOTES, 'UTF-8') . "</h1>\n        <p>New page created from Admin panel.</p>\n    </div>\n</body>\n</html>\n";

        if (file_put_contents($filePath, $template) === false) {
            $this->session->set_flashdata('error', 'Unable to create page file.');
            redirect('admin/pages');
        }

        $seoKey = ($themeFolder ? $themeFolder : 'default') . '/' . $filename;
        $this->save_theme_page_seo($seoKey, [
            'meta_title' => $safeTitle,
            'meta_keywords' => '',
            'meta_description' => '',
            'meta_robots' => '',
            'meta_tags' => '',
            'page_placement' => in_array($pagePlacement, ['none', 'header', 'footer', 'both'], true) ? $pagePlacement : 'none',
            'is_active' => $isActive === 0 ? 0 : 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->session->set_flashdata('message', 'Theme page created successfully.');
        redirect('admin/edit_theme_file?file=' . rawurlencode($filename));
    }

    public function edit_theme_file()
    {
        show_error('The online theme editor has been disabled for security reasons to mitigate Remote Code Execution (RCE) vectors. Please modify layout files securely via SFTP or Git deployment.', 403, 'Forbidden');
    }

    private function get_active_theme_folder($theme)
    {
        if ($theme == 'restaurant') {
            return 'webshop_restaurant_t1';
        }
        if ($theme == 'nw') {
            return 'nw_theme';
        }
        if ($theme == 'gulfpharmacy') {
            return 'gulfpharmacy_theme';
        }
        return '';
    }

    private function get_theme_page_seo($seo_key)
    {
        if (!is_file($this->themePageSeoStore)) {
            return [];
        }

        $json = file_get_contents($this->themePageSeoStore);
        if ($json === false || $json === '') {
            return [];
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            return [];
        }

        return isset($data[$seo_key]) && is_array($data[$seo_key]) ? $data[$seo_key] : [];
    }

    private function get_theme_page_seo_store()
    {
        if (!is_file($this->themePageSeoStore)) {
            return [];
        }

        $json = file_get_contents($this->themePageSeoStore);
        if ($json === false || $json === '') {
            return [];
        }

        $decoded = json_decode((string)$json, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function save_theme_page_seo($seo_key, array $seo_data)
    {
        $all = [];
        if (is_file($this->themePageSeoStore)) {
            $json = file_get_contents($this->themePageSeoStore);
            $decoded = json_decode((string)$json, true);
            if (is_array($decoded)) {
                $all = $decoded;
            }
        }

        $all[$seo_key] = $seo_data;

        file_put_contents(
            $this->themePageSeoStore,
            json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    private function delete_theme_page_seo_keys_by_prefix($prefix)
    {
        if (!is_file($this->themePageSeoStore)) {
            return;
        }

        $json = file_get_contents($this->themePageSeoStore);
        $decoded = json_decode((string)$json, true);
        if (!is_array($decoded)) {
            return;
        }

        foreach ($decoded as $seoKey => $seoData) {
            if (strpos((string)$seoKey, (string)$prefix) === 0) {
                unset($decoded[$seoKey]);
            }
        }

        file_put_contents(
            $this->themePageSeoStore,
            json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    private function get_seo_health_metrics()
    {
        $theme = $this->db->select('webshop_theme')->get('webshop_settings')->row();
        $themeFolder = $this->get_active_theme_folder($theme ? $theme->webshop_theme : 'default');
        $prefix = ($themeFolder ? $themeFolder : 'default') . '/';
        $reserved = ['index', 'about_us', 'contact_us', 'terms_and_conditions', 'privacy_policy', 'login', 'register', 'cart', 'checkout'];

        $themeActive = 0;
        $themeInactive = 0;
        $store = [];
        if (is_file($this->themePageSeoStore)) {
            $json = file_get_contents($this->themePageSeoStore);
            $decoded = json_decode((string)$json, true);
            if (is_array($decoded)) {
                $store = $decoded;
            }
        }

        foreach ($store as $seoKey => $seoData) {
            if (!is_array($seoData) || strpos($seoKey, $prefix) !== 0) {
                continue;
            }
            $file = substr($seoKey, strlen($prefix));
            $slug = preg_replace('/\.php$/i', '', $file);
            if ($slug === '' || in_array($slug, $reserved, true)) {
                continue;
            }

            $isActive = isset($seoData['is_active']) ? (int)$seoData['is_active'] : 1;
            if ($isActive === 1) {
                $themeActive++;
            } else {
                $themeInactive++;
            }
        }

        $dbActivePages = (int)$this->db->where('is_active', 1)->count_all_results('webshop_static_pages');
        $dbInactivePages = (int)$this->db->where('is_active', 0)->count_all_results('webshop_static_pages');
        $activeCategories = (int)$this->db->where('is_active', 1)->where('in_eshop', 1)->count_all_results('categories');
        $activeProducts = (int)$this->db->where('is_active', 1)->where('in_eshop', 1)->count_all_results('products');

        return [
            'indexed_pages' => 3 + $dbActivePages + $themeActive, // home, about, contact + dynamic active pages
            'active_categories' => $activeCategories,
            'active_products' => $activeProducts,
            'excluded_inactive_pages' => $dbInactivePages + $themeInactive,
        ];
    }

    /* ===================== BLOGS MODULE ===================== */
    public function blogs()
    {
        $blogTable = $this->resolve_blog_table();
        if ($blogTable === null) {
            $this->session->set_flashdata('error', 'Blog module database table is missing. Please create `webshop_blogs` or `sma_webshop_blogs`.');
            $this->data['blogs'] = [];
            $this->load->view($this->theme . 'admin/header', $this->data);
            $this->load->view($this->theme . 'admin/blogs', $this->data);
            $this->load->view($this->theme . 'admin/footer');
            return;
        }

        $activeTheme = $this->get_active_webshop_theme();
        $this->data['current_webshop_theme'] = $activeTheme;

        if ($this->input->get('delete')) {
            $this->db->delete($blogTable, ['id' => (int)$this->input->get('delete')]);
            redirect('admin/blogs?deleted=1');
        }

        if ($this->db->field_exists('webshop_theme', $blogTable)) {
            $this->db
                ->group_start()
                ->where('webshop_theme', $activeTheme)
                ->or_where('webshop_theme IS NULL', null, false)
                ->or_where('webshop_theme', '')
                ->group_end();
        }
        $this->data['blogs'] = $this->db->order_by('id', 'DESC')->get($blogTable)->result_array();

        $this->load->view($this->theme . 'admin/header', $this->data);
        $this->load->view($this->theme . 'admin/blogs', $this->data);
        $this->load->view($this->theme . 'admin/footer');
    }

    public function blog_edit($id = null)
    {
        $blogTable = $this->resolve_blog_table();
        if ($blogTable === null) {
            $this->session->set_flashdata('error', 'Blog module database table is missing. Please create `webshop_blogs` or `sma_webshop_blogs`.');
            redirect('admin/blogs');
        }

        $activeTheme = $this->get_active_webshop_theme();
        $this->data['current_webshop_theme'] = $activeTheme;

        if ($this->input->post()) {
            $data = [
                'title'      => $this->input->post('title'),
                'slug'       => url_title($this->input->post('title'), '-', TRUE),
                'content'    => $this->input->post('content'),
                'image'      => $this->input->post('image'),
                'is_active'  => $this->input->post('is_active') ? 1 : 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            if ($this->db->field_exists('webshop_theme', $blogTable)) {
                $data['webshop_theme'] = $activeTheme;
            }
            if ($id) {
                $this->db->update($blogTable, $data, ['id' => $id]);
            } else {
                $data['created_at'] = date('Y-m-d H:i:s');
                $this->db->insert($blogTable, $data);
            }
            redirect('admin/blogs?saved=1');
        }

        $this->data['blog'] = $id ? $this->db->get_where($blogTable, ['id' => $id])->row_array() : [];
        $this->load->view($this->theme . 'admin/header', $this->data);
        $this->load->view($this->theme . 'admin/blog_edit', $this->data);
        $this->load->view($this->theme . 'admin/footer');
    }

    private function resolve_blog_table()
    {
        if ($this->db->table_exists('webshop_blogs')) {
            return 'webshop_blogs';
        }
        if ($this->db->table_exists('sma_webshop_blogs')) {
            return 'sma_webshop_blogs';
        }
        return null;
    }

    private function get_active_webshop_theme()
    {
        $ws = $this->db->select('webshop_theme')->get('webshop_settings')->row();
        return $ws && !empty($ws->webshop_theme) ? (string)$ws->webshop_theme : 'default';
    }

    /* ===================== SEO MODULE ===================== */
    public function seo()
    {
        $page = $this->input->get('page');
        
        if ($this->input->post('save_seo')) {
            $data = [
                'meta_title'       => $this->input->post('meta_title'),
                'meta_description' => $this->input->post('meta_description'),
                'meta_keywords'    => $this->input->post('meta_keywords'),
            ];
            
            $post_page = $this->input->post('page');
            
            if (!empty($post_page)) {
                // Save page-specific SEO in JSON store (no DB table needed)
                $file = basename((string)$post_page);
                $ws = $this->db->get('webshop_settings')->row();
                $themeFolder = $this->get_active_theme_folder($ws ? $ws->webshop_theme : 'default');
                $seoKey = ($themeFolder ? $themeFolder : 'default') . '/' . $file;
                $existingSeo = $this->get_theme_page_seo($seoKey);

                $this->save_theme_page_seo($seoKey, [
                    'meta_title' => trim((string)$data['meta_title']),
                    'meta_description' => trim((string)$data['meta_description']),
                    'meta_keywords' => trim((string)$data['meta_keywords']),
                    'meta_robots' => trim((string)$this->input->post('meta_robots', true)),
                    'meta_tags' => trim((string)$this->input->post('meta_tags', false)),
                    'page_placement' => trim((string)$this->input->post('page_placement', true)) !== ''
                        ? trim((string)$this->input->post('page_placement', true))
                        : (isset($existingSeo['page_placement']) ? (string)$existingSeo['page_placement'] : 'none'),
                    'is_active' => ((int)$this->input->post('is_active') === 0) ? 0 : 1,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $this->session->set_flashdata('message', "SEO settings for '$post_page' saved successfully.");
                redirect('admin/seo?page=' . urlencode($file));
            } else {
                // Save Global SEO
                $this->db->where('id', 1)->update('webshop_settings', $data);
                $this->session->set_flashdata('message', 'Global SEO settings saved successfully.');
                redirect('admin/seo');
            }
        }

        if (!empty($page)) {
            $file = basename((string)$page);
            if (pathinfo($file, PATHINFO_EXTENSION) !== 'php') {
                $this->session->set_flashdata('error', 'Invalid page file for SEO.');
                redirect('admin/pages');
            }
            $this->data['seo_page'] = $file;
            $wsSetting = $this->db->get('webshop_settings')->row();
            $themeFolder = $this->get_active_theme_folder($wsSetting ? $wsSetting->webshop_theme : 'default');
            $seoKey = ($themeFolder ? $themeFolder : 'default') . '/' . $file;
            $pageSeo = $this->get_theme_page_seo($seoKey);

            $ws = new stdClass();
            $ws->meta_title = isset($pageSeo['meta_title']) ? $pageSeo['meta_title'] : '';
            $ws->meta_description = isset($pageSeo['meta_description']) ? $pageSeo['meta_description'] : '';
            $ws->meta_keywords = isset($pageSeo['meta_keywords']) ? $pageSeo['meta_keywords'] : '';
            $ws->meta_tags = isset($pageSeo['meta_tags']) ? $pageSeo['meta_tags'] : '';
            $ws->meta_robots = isset($pageSeo['meta_robots']) ? $pageSeo['meta_robots'] : '';
            $ws->page_placement = isset($pageSeo['page_placement']) ? $pageSeo['page_placement'] : 'none';
            $ws->is_active = isset($pageSeo['is_active']) ? (int)$pageSeo['is_active'] : 1;
            $this->data['ws'] = $ws;
        } else {
            $this->data['seo_page'] = null;
            $this->data['ws'] = $this->db->get('webshop_settings')->row();
        }
        // Fetch all theme pages for the dropdown menu
        $theme_pages = [];
        if (isset($wsSetting)) {
            $wsConfig = clone $wsSetting;
        } else {
            $wsConfig = $this->db->get('webshop_settings')->row();
        }
        $themeFolderDd = $this->get_active_theme_folder($wsConfig ? $wsConfig->webshop_theme : 'default');
        $dirPathDd = FCPATH . 'themes/default/views/webshop/' . ($themeFolderDd ? $themeFolderDd . '/' : '');
        if (is_dir($dirPathDd)) {
            $files = scandir($dirPathDd);
            foreach ($files as $file) {
                if (is_file($dirPathDd . $file) && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                    $theme_pages[] = $file;
                }
            }
        }
        $this->data['theme_pages_dropdown'] = $theme_pages;

        $this->load->view($this->theme . 'admin/header', $this->data);
        $this->load->view($this->theme . 'admin/seo', $this->data);
        $this->load->view($this->theme . 'admin/footer');
    }

    public function seo_advanced()
    {
        if ($this->input->post('save_advanced_seo')) {
            $data = [
                'og_title'       => $this->input->post('og_title'),
                'og_description' => $this->input->post('og_description'),
                'og_image'       => $this->input->post('og_image'),
                'canonical_url'  => $this->input->post('canonical_url'),
                'schema_markup'  => $this->input->post('schema_markup'),
            ];
            $this->db->where('id', 1)->update('webshop_settings', $data);

            $ext = [
                'meta_robots_default' => trim((string)$this->input->post('meta_robots_default', true)),
                'hreflang_enabled' => $this->input->post('hreflang_enabled') ? 1 : 0,
                'hreflang_codes' => trim((string)$this->input->post('hreflang_codes', true)),
                'geo_region' => trim((string)$this->input->post('geo_region', true)),
                'geo_position' => trim((string)$this->input->post('geo_position', true)),
                'geo_icbm' => trim((string)$this->input->post('geo_icbm', true)),
                'meta_copyright' => trim((string)$this->input->post('meta_copyright', true)),
                'meta_theme_color' => trim((string)$this->input->post('meta_theme_color', true)),
                'og_type_default' => trim((string)$this->input->post('og_type_default', true)),
                'inject_global_schema' => $this->input->post('inject_global_schema') ? 1 : 0,
                'homepage_pharmacy_schema' => trim((string)$this->input->post('homepage_pharmacy_schema', false)),
                'blog_faq_schema' => trim((string)$this->input->post('blog_faq_schema', false)),
                'enable_product_jsonld' => $this->input->post('enable_product_jsonld') ? 1 : 0,
                'enable_article_jsonld' => $this->input->post('enable_article_jsonld') ? 1 : 0,
                'enable_rss_link' => $this->input->post('enable_rss_link') ? 1 : 0,
                'rss_feed_title' => trim((string)$this->input->post('rss_feed_title', true)),
                'ai_entity' => trim((string)$this->input->post('ai_entity', true)),
                'ai_summary' => trim((string)$this->input->post('ai_summary', true)),
                'ai_category' => trim((string)$this->input->post('ai_category', true)),
                'ai_industry' => trim((string)$this->input->post('ai_industry', true)),
                'ai_brand' => trim((string)$this->input->post('ai_brand', true)),
                'ai_purpose' => trim((string)$this->input->post('ai_purpose', true)),
                'ai_keyphrase' => trim((string)$this->input->post('ai_keyphrase', true)),
                'ai_context' => trim((string)$this->input->post('ai_context', true)),
            ];
            $this->save_seo_extended_settings(array_merge($this->get_seo_extended_defaults(), $ext));

            $this->session->set_flashdata('message', 'Advanced SEO settings saved.');
            redirect('admin/seo_advanced');
        }

        $this->data['ws'] = $this->db->get('webshop_settings')->row();
        $this->data['seo_ext'] = $this->load_seo_extended_settings();
        $this->load->view($this->theme . 'admin/header', $this->data);
        $this->load->view($this->theme . 'admin/seo_advanced', $this->data);
        $this->load->view($this->theme . 'admin/footer');
    }

    private function get_seo_extended_defaults()
    {
        return [
            'meta_robots_default' => 'index,follow',
            'hreflang_enabled' => 0,
            'hreflang_codes' => 'en,x-default',
            'geo_region' => '',
            'geo_position' => '',
            'geo_icbm' => '',
            'meta_copyright' => '',
            'meta_theme_color' => '',
            'og_type_default' => 'website',
            'inject_global_schema' => 1,
            'homepage_pharmacy_schema' => '',
            'blog_faq_schema' => '',
            'enable_product_jsonld' => 1,
            'enable_article_jsonld' => 1,
            'enable_rss_link' => 1,
            'rss_feed_title' => 'Blog',
            'ai_entity' => '',
            'ai_summary' => '',
            'ai_category' => '',
            'ai_industry' => '',
            'ai_brand' => '',
            'ai_purpose' => '',
            'ai_keyphrase' => '',
            'ai_context' => '',
        ];
    }

    private function load_seo_extended_settings()
    {
        if (!is_file($this->seoExtendedStore)) {
            return $this->get_seo_extended_defaults();
        }
        $json = file_get_contents($this->seoExtendedStore);
        $decoded = json_decode((string)$json, true);
        if (!is_array($decoded)) {
            return $this->get_seo_extended_defaults();
        }
        return array_merge($this->get_seo_extended_defaults(), $decoded);
    }

    private function save_seo_extended_settings(array $data)
    {
        if (!is_dir(dirname($this->seoExtendedStore))) {
            return;
        }
        file_put_contents(
            $this->seoExtendedStore,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    /* ===================== REPORTS MODULE ===================== */
    public function reports()
    {
        // Total sales (orders) - using sma_orders (POS table)
        $this->data['total_orders']   = $this->db->count_all_results('sma_orders');
        $this->data['total_revenue']  = $this->db->select_sum('grand_total')->get('sma_orders')->row()->grand_total ?? 0;
        $this->data['total_customers']= $this->db->where('group_name', 'customer')->count_all_results('sma_companies');
        $this->data['pending_orders'] = $this->db->where('payment_status', 'pending')->count_all_results('sma_orders');

        // Orders by month (last 6 months)
        $monthly = $this->db->query("
            SELECT DATE_FORMAT(date, '%b %Y') as month, COUNT(*) as count, SUM(grand_total) as revenue
            FROM sma_orders
            WHERE date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY YEAR(date), MONTH(date)
            ORDER BY date ASC
        ")->result_array();
        $this->data['monthly_stats'] = $monthly;

        // Top 5 products by sales (from sale_items)
        $top_products = $this->db->query("
            SELECT p.name, SUM(si.quantity) as total_qty
            FROM sma_sale_items si
            JOIN sma_products p ON p.id = si.product_id
            GROUP BY si.product_id
            ORDER BY total_qty DESC
            LIMIT 5
        ")->result_array();
        $this->data['top_products'] = $top_products;

        $this->load->view($this->theme . 'admin/header', $this->data);
        $this->load->view($this->theme . 'admin/reports', $this->data);
        $this->load->view($this->theme . 'admin/footer');
    }

    /* ===================== EMAIL SETTINGS MODULE ===================== */
    public function email_settings()
    {
        if ($this->input->post('save_email')) {
            $data = [
                'smtp_host'       => $this->input->post('smtp_host'),
                'smtp_user'       => $this->input->post('smtp_user'),
                'smtp_pass'       => $this->input->post('smtp_pass'),
                'smtp_port'       => $this->input->post('smtp_port'),
                'smtp_encryption' => $this->input->post('smtp_encryption'),
                'from_email'      => $this->input->post('from_email'),
                'from_name'       => $this->input->post('from_name'),
            ];
            $this->db->where('id', 1)->update('webshop_settings', $data);
            $this->session->set_flashdata('message', 'Email settings updated.');
            redirect('admin/email_settings');
        }

        $this->data['ws'] = $this->db->get('webshop_settings')->row();
        $this->load->view($this->theme . 'admin/header', $this->data);
        $this->load->view($this->theme . 'admin/email_settings', $this->data);
        $this->load->view($this->theme . 'admin/footer');
    }
}
