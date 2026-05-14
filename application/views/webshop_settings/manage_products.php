<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<style>
    .mp-container { padding: 25px; background: #f8fafc; font-family: 'Inter', 'Segoe UI', Roboto, sans-serif; min-height: 100vh; }
    .mp-card { background: #fff; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05), 0 8px 10px -6px rgba(0,0,0,0.05); margin-bottom: 30px; overflow: hidden; }
    .mp-header { padding: 20px 30px; background: #fff; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; }
    .mp-title { margin: 0; font-size: 20px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 12px; }
    .mp-title i { color: #2563eb; background: #eff6ff; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: 10px; font-size: 18px; }
    .mp-body { padding: 30px; }
    
    /* Category List Styling */
    .cat-list-wrapper { border: 1px solid #f1f5f9; border-radius: 12px; overflow: hidden; background: #fff; height: calc(100vh - 200px); display: flex; flex-direction: column; position: sticky; top: 20px; }
    .cat-search { padding: 15px; border-bottom: 1px solid #f1f5f9; background: #f8fafc; }
    .cat-search input { border-radius: 8px; border: 1px solid #e2e8f0; font-size: 14px; padding: 10px 15px; transition: all 0.2s; }
    .cat-search input:focus { border-color: #3b82f6; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1); outline: none; }
    .cat-list { flex-grow: 1; overflow-y: auto; padding: 5px; }
    .cat-item { display: flex; align-items: center; padding: 12px 15px; border-radius: 8px; margin-bottom: 2px; transition: all 0.2s; cursor: pointer; text-decoration: none !important; color: #475569; border: 1px solid transparent; }
    .cat-item:hover { background: #f8fafc; color: #2563eb; }
    .cat-item.active { background: #eff6ff; color: #2563eb; border-color: #dbeafe; font-weight: 600; }
    .cat-check { margin-right: 12px; transform: scale(1.1); cursor: pointer; }
    .cat-status { margin-left: auto; display: flex; gap: 8px; align-items: center; }
    
    /* Subcategory Chips */
    .subcat-section { margin-bottom: 35px; background: #fff; padding: 20px; border-radius: 12px; border: 1px solid #f1f5f9; }
    .subcat-grid { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 15px; }
    .subcat-chip-container { display: flex; align-items: center; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 100px; padding: 6px 16px; gap: 8px; transition: all 0.2s; cursor: pointer; }
    .subcat-chip-container:hover { border-color: #cbd5e1; background: #f1f5f9; }
    .subcat-chip-container.active { border-color: #3b82f6; background: #eff6ff; color: #2563eb; }
    
    /* Product Table Styling */
    .mp-table-wrapper { border-radius: 12px; border: 1px solid #f1f5f9; overflow: hidden; background: #fff; }
    .mp-table { border: none !important; margin-bottom: 0; width: 100%; border-collapse: separate; border-spacing: 0; }
    .mp-table thead th { position: sticky; top: 0; z-index: 10; background: #f8fafc !important; border-bottom: 2px solid #e2e8f0 !important; padding: 15px !important; color: #64748b; font-weight: 700; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; }
    .mp-table tbody td { padding: 15px !important; vertical-align: middle !important; border-top: 1px solid #f1f5f9 !important; font-size: 14px; color: #1e293b; }
    .mp-table tbody tr:hover { background: #f8fafc; }
    .prod-img { width: 50px; height: 50px; object-fit: cover; border-radius: 8px; border: 1px solid #f1f5f9; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
    .prod-code { font-family: 'JetBrains Mono', 'Monaco', 'Consolas', monospace; font-size: 12px; color: #4f46e5; background: #eef2ff; padding: 4px 8px; border-radius: 6px; border: 1px solid #e0e7ff; font-weight: 500; }
    .storage-badge { background: #f1f5f9; color: #475569; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 500; }
</style>

<div class="mp-container">
    <div class="mp-card">
        <div class="mp-header">
            <h2 class="mp-title"><i class="fa fa-cubes"></i> <?= lang('Manage Products For E-Shop & E-commerce'); ?></h2>
        </div>
        <div class="mp-body">
            <div class="row">
                <!-- Left Sidebar: Categories -->
                <div class="col-sm-4">
                    <div class="cat-list-wrapper">
                        <div class="cat-search">
                            <input type="text" id="categorySearch" class="form-control" placeholder="Search categories...">
                        </div>
                        <div class="cat-list">
                            <?php if(is_array($categories)): ?>
                                <?php foreach ($categories as $category): 
                                    $cm = 'webshop_settings/manage_products';
                                    $is_active_cat = ($category['id'] == $category_id);
                                    $checked = ($category['in_eshop'] || $is_active_cat) ? ' checked="checked" ' : '';
                                    $status_html = $category['in_eshop'] 
                                        ? '<a href="'.base_url($cm.'/'.$category['id']).'"><i class="fa fa-list-ul text-success"></i></a>' 
                                        : '<i class="fa fa-ban text-danger"></i>';
                                ?>
                                    <div class="cat-item <?= $is_active_cat ? 'active' : '' ?>" data-id="<?= $category['id'] ?>">
                                        <input type="checkbox" name="categories[]" value="<?= $category['id'] ?>" <?= $checked ?> parent="0" class="checkbox eshop_categories" />
                                        <a href="<?= base_url($cm.'/'.$category['id']) ?>" style="color:inherit; flex-grow:1; margin-left: 10px;"><?= $category['name'] ?></a>
                                        <div class="cat-status eshop_category_<?= $category['id'] ?>"><?= $status_html ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Right Content: Subcategories & Products -->
                <div class="col-sm-8">
                    <!-- Subcategories as Chips -->
                    <?php if((bool)$subcategories): ?>
                        <div class="subcat-section">
                            <h4 style="font-size: 14px; font-weight: 600; color: #64748b; margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
                                <i class="fa fa-folder-open-o"></i> Subcategories
                            </h4>
                            <div class="subcat-grid">
                                <?php foreach ($subcategories as $subcategory): 
                                    $subchecked = ($subcategory['in_eshop']) ? ' checked="checked" ' : '';
                                ?>
                                    <div class="subcat-chip-container <?= $subcategory['in_eshop'] ? 'active' : '' ?>">
                                        <input type="checkbox" name="categories[]" value="<?= $subcategory['id'] ?>" <?= $subchecked ?> parent="<?= $subcategory['parent_id'] ?>" class="checkbox eshop_categories parent_<?= $subcategory['parent_id'] ?>" id="categories_<?= $subcategory['id'] ?>" />
                                        <label for="categories_<?= $subcategory['id'] ?>" style="margin:0; cursor:pointer; font-weight: 500;"><?= $subcategory['name'] ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Products Table -->
                    <div class="mp-table-wrapper">
                        <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                            <table class="table mp-table">
                                <thead>
                                    <tr>
                                        <th style="width: 40px; text-align: center;"><input type="checkbox" id="all_products" class="checkbox" /></th>
                                        <th style="width: 80px; text-align: center;">Image</th>
                                        <th>Code</th>
                                        <th>Product Name</th>
                                        <th>Storage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if(is_array($products)): ?>
                                    <?php foreach ($products as $product): 
                                        $pchecked = $product->in_eshop == 1 ? ' checked="checked" ' : '';                       
                                        $product_image = ($product->image == '') ? 'no_image.png' : $product->image;
                                    ?>
                                        <tr class="prdcat_<?= $product->category_id ?> prdsubcat_<?= $product->subcategory_id ?>">
                                            <td style="text-align: center;"><input type="checkbox" name="products[]" value="<?= $product->id ?>" <?= $pchecked ?> variant="0" class="checkbox eshop_product prd_chk" /></td>
                                            <td style="text-align: center;"><img src="<?= $this->webshop_api_model->get_media_uploads_base() . $product_image ?>" class="prod-img" alt="<?= $product->code ?>" /></td>
                                            <td><span class="prod-code"><?= $product->code ?></span></td>
                                            <td style="font-weight: 700; color: #0f172a;"><?= $product->name ?></td>                        
                                            <td><span class="storage-badge"><?= $product->storage_type ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center" style="padding: 100px !important; color: #94a3b8; background: #fff;">
                                            <div style="background: #f8fafc; width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                                                <i class="fa fa-shopping-basket fa-2x" style="opacity: 0.3;"></i>
                                            </div>
                                            <p style="font-size: 16px; font-weight: 500; color: #64748b;">Select a category to manage products</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.getElementById('categorySearch').addEventListener('input', function(e) {
        let term = e.target.value.toLowerCase();
        document.querySelectorAll('.cat-item').forEach(item => {
            let name = item.innerText.toLowerCase();
            item.style.display = name.includes(term) ? 'flex' : 'none';
        });
    });
</script>

<script>
    $(document).ready(function () {
                
        
        $('input.eshop_categories').on('ifToggled', function(event){
            var checked = $(this).is(":checked");
            let eshop_status = 0;
            let category_id = $(this).val();
            let parent_id   = $(this).attr('parent');
                
            if(checked) {
                eshop_status = 1;
            }
            
            manage_eshop_category(category_id, parent_id, eshop_status);
            
        });
        
        $('input.eshop_product').on('ifToggled', function(event){
            var checked = $(this).is(":checked");
            let eshop_status = 0;
            let product_id   = $(this).val();
            let variant_id   = $(this).attr('variant');
                
            if(checked) {
                eshop_status = 1;
            }
            
            manage_eshop_product(product_id, variant_id, eshop_status);
            
        });
        
        
        $('input#all_products').on('ifToggled', function(event){
            var checked = $(this).is(":checked");
             
            if(checked) {
                $('input.prd_chk').iCheck('check');
            } else {
                $('input.prd_chk').iCheck('uncheck');
            }
        });
        
               
    });
    
    function manage_eshop_category(category_id, parent_id, eshop_status){        
                 
        var callurl = '<?= base_url('webshop_settings/webshop_ajax_request')?>';
        
        var postData = 'action=manage_eshop_category';
            postData = postData + '&category_id=' + category_id;
            postData = postData + '&parent_id=' + parent_id;
            postData = postData + '&eshop_status=' + eshop_status;
          // alert(postData);
        $.ajax({
            type: "POST",
            url: callurl ,
            data: postData,
            beforeSend: function () {
                //$("#top-cart-wishlist-count").html("<div class='overlay'><i class='fa fa-refresh fa-spin'></i> Adding In Wishlist</div>");
            },
            success: function (data) {

                var objData = JSON.parse(data);
                if(objData.status == "SUCCESS") {
                    
                    if(parent_id == 0) {
                        var in_eshop;
                        if(eshop_status) {
                            $(".prdcat_"+category_id).show();
                            in_eshop = '<a href="'+'<?=base_url('webshop_settings/manage_products/')?>'+category_id+'"><i class="fa fa-list text-success"></i></a>' ;
                        } else {
                            $(".prdcat_"+category_id).hide(); 
                            in_eshop = '<i class="fa fa-ban text-danger"></i>';
                        }
                        
                        $('.eshop_category_'+category_id).html(in_eshop);
                        
                    } else {
                        if(eshop_status) {
                            $(".prdsubcat_"+category_id).show(); 
                        } else {
                            $(".prdsubcat_"+category_id).hide(); 
                        }
                    }
                }
            }
        });
    }
        
    function manage_eshop_product(product_id, variant_id, eshop_status){        
                 
        var callurl = '<?= base_url('webshop_settings/webshop_ajax_request')?>';
        
        var postData = 'action=manage_eshop_product';
            postData = postData + '&product_id=' + product_id;
            postData = postData + '&variant_id=' + variant_id;
            postData = postData + '&eshop_status=' + eshop_status;
          //  alert(postData);
        $.ajax({
            type: "POST",
            url: callurl ,
            data: postData,
            beforeSend: function () {
                //$("#top-cart-wishlist-count").html("<div class='overlay'><i class='fa fa-refresh fa-spin'></i> Adding In Wishlist</div>");
            },
            success: function (data) {

//                var objData = JSON.parse(data);
//                if(objData.status == "SUCCESS") {
//                    
//                     
//                }
            }
        });
        
    
    }
    
</script>    