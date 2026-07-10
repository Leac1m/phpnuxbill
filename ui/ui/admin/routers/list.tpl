{include file="sections/header.tpl"}
<!-- routers -->

<div class="row">
    <div class="col-sm-12">
        <div class="panel panel-hovered mb20 panel-primary">
            <div class="panel-heading">{Lang::T('Routers')}
                <div class="btn-group pull-right">
                    <a class="btn btn-primary btn-xs" title="save" href="{Text::url('')}routers/maps">
                        <span class="glyphicon glyphicon-map-marker"></span></a>
                </div>
            </div>
            <div class="panel-body">
                <div class="md-whiteframe-z1 mb20 text-center" style="padding: 15px">
                    <div class="col-md-8">

                        <form id="site-search" method="post" action="{Text::url('')}routers/list/">
                            <div class="input-group">
                                <div class="input-group-addon">
                                    <span class="fa fa-search"></span>
                                </div>
                                <input type="text" name="name" class="form-control"
                                    placeholder="{Lang::T('Search by Name')}...">
                                <div class="input-group-btn">
                                    <button class="btn btn-success" type="submit">{Lang::T('Search')}</button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="col-md-4">
                        <div class="btn-group btn-group-justified" role="group">
                            <a href="{Text::url('')}routers/add" class="btn btn-primary"><i class="ion ion-android-add"></i> {Lang::T('New Router')}</a>
                            <a href="javascript:void(0)" onclick="generateProvisionToken()" class="btn btn-success"><i class="glyphicon glyphicon-flash"></i> Auto-Provision</a>
                        </div>
                    </div>&nbsp;
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-condensed">
                        <thead>
                            <tr>
                                <th>{Lang::T('Router Name')}</th>
                                <th>{Lang::T('IP Address')}</th>
                                <th>{Lang::T('Username')}</th>
                                <th>{Lang::T('Description')}</th>
                                {if $_c['router_check']}
                                    <th>{Lang::T('Online Status')}</th>
                                    <th>{Lang::T('Last Seen')}</th>
                                {/if}
                                <th>{Lang::T('Status')}</th>
                                <th>{Lang::T('Manage')}</th>
                                <th>ID</th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach $d as $ds}
                                <tr {if $ds['enabled'] !=1}class="danger" title="disabled" {/if}>
                                    <td>
                                        {if $ds['coordinates']}
                                            <a href="https://www.google.com/maps/dir//{$ds['coordinates']}/" target="_blank"
                                                class="btn btn-default btn-xs" title="{$ds['coordinates']}"><i
                                                    class="glyphicon glyphicon-map-marker"></i></a>
                                        {/if}
                                        {$ds['name']}
                                    </td>
                                    <td style="background-color: black; color: black;"
                                        onmouseleave="this.style.backgroundColor = 'black';"
                                        onmouseenter="this.style.backgroundColor = 'white';">{$ds['ip_address']}</td>
                                    <td style="background-color: black; color: black;"
                                        onmouseleave="this.style.backgroundColor = 'black';"
                                        onmouseenter="this.style.backgroundColor = 'white';">{$ds['username']}</td>
                                    <td>{$ds['description']}</td>
                                    {if $_c['router_check']}
                                        <td>
                                            <span
                                                class="label {if $ds['status'] == 'Online'}label-success {else}label-danger {/if}">
                                                {if $ds['status'] == 'Online'}
                                                    {Lang::T('Online')}
                                                {else}
                                                    {Lang::T('Offline')}
                                                {/if}
                                            </span>
                                        </td>
                                        <td>{$ds['last_seen']}</td>
                                    {/if}
                                    <td>{if $ds['enabled'] == 1}{Lang::T('Enabled')}{else}{Lang::T('Disabled')}{/if}</td>
                                    <td>
                                        <a href="{Text::url('')}routers/edit/{$ds['id']}"
                                            class="btn btn-info btn-xs">{Lang::T('Edit')}</a>
                                        <a href="{Text::url('')}routers/delete/{$ds['id']}" id="{$ds['id']}"
                                            onclick="return ask(this, '{Lang::T('Delete')}?')"
                                            class="btn btn-danger btn-xs"><i class="glyphicon glyphicon-trash"></i></a>
                                    </td>
                                    <td>{$ds['id']}</td>
                                </tr>
                            {/foreach}
                        </tbody>
                    </table>
                </div>
                {include file="pagination.tpl"}
                <div class="bs-callout bs-callout-info" id="callout-navbar-role">
                    <h4>{Lang::T('Check if Router Online?')}</h4>
                    <p>{Lang::T('To check whether the Router is online or not, please visit the following page')} <a
                            href="{Text::url('')}settings/miscellaneous#router_check" target="_blank"
                            class="btn btn-link">{Lang::T('Cek Now')}</a></p>
                </div>
            </div>
        </div>
    </div>
</div>


{include file="sections/footer.tpl"}

<div class="modal fade" id="provisionModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title">Auto-Provision Router</h4>
      </div>
      <div class="modal-body">
        <p>Run the following command in your MikroTik terminal to automatically connect it to PHPNuxBill:</p>
        <textarea id="provisionCmd" class="form-control" rows="3" readonly></textarea>
        <br>
        <p class="text-info"><i class="glyphicon glyphicon-refresh" id="prov-spinner"></i> Waiting for router connection...</p>
      </div>
    </div>
  </div>
</div>

<script>
let checkInterval;
function generateProvisionToken() {
    $('#provisionModal').modal('show');
    $('#provisionCmd').val('Generating command...');
    
    $.post('{Text::url('')}routers/generate_token', function(data) {
        if(data.status == 'success') {
            $('#provisionCmd').val('/tool fetch url="' + data.url + '" mode=http keep-result=yes dst-path=setup.rsc; /import setup.rsc');
            
            if(checkInterval) clearInterval(checkInterval);
            checkInterval = setInterval(function() {
                $.get('{Text::url('')}routers/check_token&token=' + data.token, function(res) {
                    if (res.status == 'connected') {
                        clearInterval(checkInterval);
                        alert("Router successfully connected!");
                        location.reload();
                    }
                });
            }, 3000);
        } else {
            $('#provisionCmd').val('Error generating token: ' + data.error);
        }
    });
}
$('#provisionModal').on('hidden.bs.modal', function () {
    if(checkInterval) clearInterval(checkInterval);
});
</script>