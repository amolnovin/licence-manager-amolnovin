(function($){
  function productModal(){ return $('#wplm-product-modal'); }
  function form(){ return $('#wplm-product-form'); }
  function openModal(editData){
    var $m = productModal(), $f = form();
    $f[0].reset();
    $f.find('[name="id"]').val('0');
    $f.find('[name="version"]').val('1.0.0');
    $f.find('[name="currency"]').val('IRT');
    $f.find('[name="price"]').val('0');
    $f.find('[name="is_visible"]').prop('checked', true);
    $('[data-wplm-modal-title]').text('افزودن محصول جدید');
    $('[data-wplm-cover-preview]').empty();
    $('[data-wplm-ajax-message]').text('');
    setProductEditor('');
    if(editData){
      $('[data-wplm-modal-title]').text('ویرایش محصول');
      Object.keys(editData).forEach(function(k){ if(k !== 'description' && k !== 'is_visible') $f.find('[name="'+k+'"]').val(editData[k]); });
      $f.find('[name="is_visible"]').prop('checked', String(editData.is_visible) === '1');
      setProductEditor(editData.description || '');
      updatePreview(editData.cover_url || '');
    }
    $m.addClass('is-open').attr('aria-hidden','false');
  }
  function closeModal(){ productModal().removeClass('is-open').attr('aria-hidden','true'); }
  function setProductEditor(content){
    content = content || '';
    if(window.tinymce && tinymce.get('wplm_product_description_editor')){
      tinymce.get('wplm_product_description_editor').setContent(content);
    }
    $('#wplm_product_description_editor').val(content);
  }
  function updatePreview(url){
    var $p = $('[data-wplm-cover-preview]');
    if(url){ $p.html('<img src="'+ $('<div>').text(url).html() +'" alt="">'); } else { $p.empty(); }
  }
  $(document).on('click','[data-wplm-open-product-modal]',function(){ openModal(); });
  $(document).on('click','[data-wplm-close-product-modal]', closeModal);
  $(document).on('click','[data-wplm-edit-product]',function(){
    var d = $(this).data();
    delete d.wplmEditProduct;
    openModal(d);
  });
  $(document).on('input','#wplm-product-form [name="cover_url"]',function(){ updatePreview($(this).val()); });
  $(document).on('blur','.wplm-form input[name="name"]', function(){
    var $form = $(this).closest('form'), $slug = $form.find('input[name="slug"]');
    if($slug.length && !$slug.val()) $slug.val($(this).val().toLowerCase().trim().replace(/\s+/g,'-').replace(/[^a-z0-9\-آ-ی]/g,''));
  });
  $(document).on('click','[data-wplm-media]',function(e){
    e.preventDefault();
    var frame = wp.media({ title:'انتخاب تصویر شاخص', button:{text:'انتخاب تصویر'}, multiple:false });
    frame.on('select', function(){
      var img = frame.state().get('selection').first().toJSON();
      var url = img.url || '';
      form().find('[name="cover_url"]').val(url);
      updatePreview(url);
    });
    frame.open();
  });
  $(document).on('submit','#wplm-product-form',function(e){
    e.preventDefault();
    var $f=$(this), $msg=$('[data-wplm-ajax-message]');
    if(window.tinymce){ tinymce.triggerSave(); }
    $msg.text('در حال ذخیره...');
    var data=$f.serializeArray().filter(function(item){ return item.name !== 'action'; });
    data.push({name:'action', value:'wplm_ajax_save_product'});
    data.push({name:'nonce', value:(window.WPLM_ADMIN||{}).nonce || ''});
    $.post((window.WPLM_ADMIN||{}).ajaxUrl || ajaxurl, data).done(function(res){
      if(res && res.success){ $msg.text((window.WPLM_ADMIN||{}).saved || 'ذخیره شد'); setTimeout(function(){ location.reload(); }, 700); }
      else { $msg.text((res && res.data && res.data.message) ? res.data.message : ((window.WPLM_ADMIN||{}).error || 'خطا')); }
    }).fail(function(){ $msg.text((window.WPLM_ADMIN||{}).error || 'خطا'); });
  });
})(jQuery);

(function($){
  function licenseModal(){ return $('#wplm-license-modal'); }
  function licenseForm(){ return $('#wplm-license-form'); }
  function openLicenseModal(data){
    var $m = licenseModal(), $f = licenseForm();
    if(!$m.length || !$f.length){ return; }
    $f[0].reset();
    $f.find('[name="id"]').val('0');
    $f.find('[name="allowed_domains"]').val('1');
    $f.find('[name="status"]').val('active');
    $('[data-wplm-license-modal-title]').text('ایجاد لایسنس دستی');
    toggleMachineField('');
    if(data){
      $('[data-wplm-license-modal-title]').text('ویرایش لایسنس');
      Object.keys(data).forEach(function(k){
        if(k === 'wplmEditLicense'){ return; }
        $f.find('[name="'+k+'"]').val(data[k]);
      });
    }
    syncLicenseProductType();
    $m.addClass('is-open').attr('aria-hidden','false');
  }

  // نمایش یا پنهان‌سازی فیلد machine_ids براساس نوع محصول انتخاب‌شده.
  function syncLicenseProductType(){
    var $sel = licenseForm().find('[name="product_id"]');
    var type = $sel.length ? ($sel.find('option:selected').data('type') || '') : '';
    toggleMachineField(type);
  }

  function toggleMachineField(type){
    if(type === 'windows'){
      $('[data-wplm-machine-field]').show();
      licenseForm().find('[name="domains"]').closest('label').hide();
    } else {
      $('[data-wplm-machine-field]').hide();
      licenseForm().find('[name="domains"]').closest('label').show();
    }
  }

  $(document).on('change','#wplm-license-form [name="product_id"]', syncLicenseProductType);

  function closeLicenseModal(){ licenseModal().removeClass('is-open').attr('aria-hidden','true'); }
  $(document).on('click','[data-wplm-open-license-modal]',function(){ openLicenseModal(); });
  $(document).on('click','[data-wplm-edit-license]',function(){ openLicenseModal($(this).data()); });
  $(document).on('click','[data-wplm-close-license-modal]', closeLicenseModal);
})(jQuery);
