<div>
    <div class="container" style="padding: 30px 0;">
    <div class="row">
        <div class="panel panel-default">
            <div class="panel-heading">
                Sale Setting
            </div>
            <div class="panel-body">
                @if(Session::has('message'))
                <div class="alert alert-success" role="alert">{{Session::get('message')}}</div>
                @endif
                <form class="form-horizontal" wire:submit.prevent="updateSale">
    
                    <div class="form-group">
                        <label class="col-md-4 control">Status</label>
                        <div class="col-md-4">
                        <select class="form-control" wire:model="status">
                            <option value="0">Inactive</option>
                            <option value="1">Active</option>
                        </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-4 control">Sale Date</label>
                        <div class="col-md-4">
                       <input type="text" id="sale-date" placeholder="YYYY/MM/DD H:M:S" class="form-conttrol input-md" wire:model="sale_date"/>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-md-4 control"></label>
                        <div class="col-md-4">
                            <BUtton type="submit" class="btn btn-primary">Update</BUtton>
                        </div>
                    </div>

                </form>

            </div>
        </div>
    </div>
    </div>
    </div>
</div>
@push('scripts')
<script>
    $(function(){
        $('#sale-date').datetimepicker({
            format : 'Y-MM-DD h:m:s',
        })
        .on('dp.change',function(ev){
            var data = $('#sale-date').val();
            @this.set('sale_date',data);
        });
    });
</script>
@endpush