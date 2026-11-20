  </div>
  <footer class="a-foot">&copy; <?= date('Y') ?> <?= e(setting('company_name')) ?> · Powered by <a href="https://digitalweboracleict.com" target="_blank" rel="noopener">DWO</a></footer>
</div>

<nav class="a-tabbar">
  <a href="<?= url('admin/index.php') ?>" class="<?= $cur === 'index.php' ? 'active' : '' ?>"><i class="fa-solid fa-gauge-high"></i><span>Home</span></a>
  <a href="<?= url('admin/orders.php') ?>" class="<?= in_array($cur, ['orders.php', 'order-view.php'], true) ? 'active' : '' ?>"><i class="fa-solid fa-receipt"></i><span>Orders</span><?php if ($cnt['orders']): ?><b><?= $cnt['orders'] ?></b><?php endif; ?></a>
  <a href="<?= url('admin/product-edit.php') ?>" class="a-tab-add"><i class="fa-solid fa-plus"></i></a>
  <a href="<?= url('admin/products.php') ?>" class="<?= in_array($cur, ['products.php', 'product-edit.php'], true) ? 'active' : '' ?>"><i class="fa-solid fa-box"></i><span>Products</span></a>
  <button type="button" data-a-open><i class="fa-solid fa-bars"></i><span>More</span></button>
</nav>

<script>
(function(){
  var side=document.getElementById('aside'),ov=document.querySelector('.a-overlay');
  function o(){side.classList.add('show');ov.classList.add('show');document.body.style.overflow='hidden';}
  function c(){side.classList.remove('show');ov.classList.remove('show');document.body.style.overflow='';}
  document.querySelectorAll('[data-a-open]').forEach(function(b){b.addEventListener('click',o);});
  document.querySelectorAll('[data-a-close]').forEach(function(b){b.addEventListener('click',c);});
  document.querySelectorAll('[data-confirm]').forEach(function(el){
    el.addEventListener(el.tagName==='FORM'?'submit':'click',function(e){if(!confirm(el.getAttribute('data-confirm')))e.preventDefault();});
  });
  // image preview for file inputs
  document.querySelectorAll('input[type=file][data-preview]').forEach(function(inp){
    inp.addEventListener('change',function(){
      var box=document.getElementById(inp.dataset.preview);if(!box)return;box.innerHTML='';
      Array.prototype.forEach.call(inp.files,function(f){var r=new FileReader();r.onload=function(ev){var i=document.createElement('img');i.src=ev.target.result;box.appendChild(i);};r.readAsDataURL(f);});
    });
  });
  // auto slug
  var nm=document.querySelector('[data-slug-src]'),sl=document.querySelector('[data-slug]');
  if(nm&&sl){nm.addEventListener('input',function(){if(sl.dataset.touched)return;sl.value=nm.value.toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'');});sl.addEventListener('input',function(){sl.dataset.touched=1;});}
})();
</script>
<?php if (!empty($useEditor)): ?>
<link href="<?= url('assets/vendor/quill/quill.snow.css') ?>" rel="stylesheet">
<script src="<?= url('assets/vendor/quill/quill.min.js') ?>"></script>
<script>
if(window.Quill)document.querySelectorAll('textarea[data-editor]').forEach(function(ta){
  var box=document.createElement('div');box.className='editor';ta.parentNode.insertBefore(box,ta);ta.style.display='none';
  var q=new Quill(box,{theme:'snow',modules:{toolbar:[[{header:[2,3,false]}],['bold','italic','underline'],[{list:'ordered'},{list:'bullet'}],['link','blockquote'],['clean']]}});
  q.root.innerHTML=ta.value;
  ta.form.addEventListener('submit',function(){ta.value=q.root.innerHTML==='<p><br></p>'?'':q.root.innerHTML;});
});
</script>
<?php endif; ?>
<?php if (!empty($extraJs)) echo $extraJs; ?>
</body>
</html>
