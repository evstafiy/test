<?php

namespace App\Models\Acts;

use App\Models\User;
use App\Models\Enterprise\EntActWork;
use App\Models\Enterprise\Enterprise;
use App\Models\Equip\DictEquip;
use App\Models\Equip\Equip;
use App\Models\Order;
use App\Models\OrderProblem;
use App\Models\Vehicle\Vehicle;
use App\Utils;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Act extends Model
{
    const TBL_MODEL = 'acts';

    protected $table = Act::TBL_MODEL;

    public $timestamps = false;

    function __construct()
    {
        $this->id = null;
        $this->id_ord_problem = null;
        $this->id_creator  = Auth::user()->id;
        $this->id_changer  = null;
        $this->id_user_it  = null;

        $this->work = 1;

        $this->date_create = Carbon::now()->timestamp;
        $this->date_work  = null;
        $this->date_close  = null;
        $this->downtime  = null;

        $this->place_work  = '';
        $this->distance_work  = '';

        $this->note  = '';
        $this->violations  = '';
        $this->violated  = 0;

        $this->equip_efficient  = 0;

        $this->resp_person  = null;
        $this->resp_person_absent  = 0;

        $this->closed = 0;
        $this->confirmed = 0;

        $this->deleted = 0;
    }

    protected $casts = [
        'equip_efficient' => 'boolean',
        'resp_person_absent' => 'boolean',
        'violated' => 'boolean',
        'deleted' => 'boolean'
    ];

    protected $guarded = [
        'id_enterprise',
        'name_enterprise'
    ];

    public function toString()
    {
        return $this->getWorkSting() . ' (' . Utils::toClientDateTime($this->date_work)  . ')';
    }

    public function getWorkSting()
    {
        switch ($this->work) {
            case 1: return 'Установка';
            case 2: return 'Осмотр';
            case 3: return 'Демонтаж';
            default: return '';
        }
    }

    public function getActWork()
    {
        return ActWork
            ::join(Act::TBL_MODEL, 
				ActWork::TBL_MODEL . '.id_act', '=', Act::TBL_MODEL . '.id')
            ->where(Act::TBL_MODEL . '.id', '=', $this->id)
            ->where(ActWork::TBL_MODEL . '.deleted', 0)
            ->select(ActWork::TBL_MODEL.'.*')
            ->get();
    }

    public function getDoneEntActWorks()
    {
        return EntActWork
            ::rightJoin(DictActWork::TBL_MODEL, function($join) {
                $join->on(EntActWork::TBL_MODEL . '.id_dict_act_work', '=', DictActWork::TBL_MODEL . '.id');
                $join->where(EntActWork::TBL_MODEL . '.id_enterprise', '=', $this->id_enterprise);
            })
            ->join(ActWork::TBL_ACT_DONE_WORKS, ActWork::TBL_ACT_DONE_WORKS . '.id_work', '=', DictActWork::TBL_MODEL . '.id')
            ->join(ActWork::TBL_MODEL, ActWork::TBL_ACT_DONE_WORKS . '.id_act_work', '=', ActWork::TBL_MODEL . '.id')
            ->join(Act::TBL_MODEL, ActWork::TBL_MODEL . '.id_act', '=', Act::TBL_MODEL . '.id')
            ->where(ActWork::TBL_ACT_DONE_WORKS . '.deleted', 0)
            ->where(Act::TBL_MODEL . '.id', $this->id)
            ->select([
                EntActWork::TBL_MODEL . '.*',
                DictActWork::TBL_MODEL . '.def_name',
                DictActWork::TBL_MODEL . '.def_price'
            ])
            ->get();
    }

    public function getEquips()
    {
        $query1 =  Equip
            ::join(ActWork::TBL_MODEL, 
				ActWork::TBL_MODEL . '.id_curr_equip', '=', Equip::TBL_MODEL . '.id')
            ->join(Act::TBL_MODEL, 
				ActWork::TBL_MODEL . '.id_act', '=',  Act::TBL_MODEL . '.id')
            ->join(DictEquip::TBL_MODEL, 
				Equip::TBL_MODEL . '.id_dict', '=', DictEquip::TBL_MODEL. '.id')
            ->where(Act::TBL_MODEL . '.id', $this->id)
            ->where(ActWork::TBL_MODEL . '.deleted', 0)
            ->where(ActWork::TBL_MODEL . '.work', 1)
            ->where(Equip::TBL_MODEL . '.owner', 1)
            ->select([
                Equip::TBL_MODEL . '.*',
                DictEquip::TBL_MODEL . '.name_dict'
            ])
            ->get();

        $query2 = Equip
            ::join(ActWork::TBL_MODEL, 
				ActWork::TBL_MODEL . '.id_repl_equip', '=', Equip::TBL_MODEL . '.id')
            ->join(Act::TBL_MODEL, 
				ActWork::TBL_MODEL . '.id_act', '=',  Act::TBL_MODEL . '.id')
            ->join(DictEquip::TBL_MODEL, 
				Equip::TBL_MODEL . '.id_dict', '=', DictEquip::TBL_MODEL. '.id')
            ->where(Act::TBL_MODEL . '.id', $this->id)
            ->where(ActWork::TBL_MODEL . '.deleted', 0)
            ->where(ActWork::TBL_MODEL . '.work', 2)
            ->where(ActWork::TBL_MODEL . '.guaranted', 0)
            ->where(Equip::TBL_MODEL . '.owner', 1)
            ->select([
                Equip::TBL_MODEL . '.*',
                DictEquip::TBL_MODEL . '.name_dict'
            ])
            ->get();

        return $query1->merge($query2);
    }

    public function getStatusString()
    {
        switch ($this->status) {
            case 1: return 'Подтвержден';
            case 2: return 'Закрыт';
            default: return 'В работе';
        }
    }

    public function getOrderProblem()
    {
        $ord_problem = OrderProblem::find($this->id_ord_problem);

        if ($ord_problem != null) {
            return $ord_problem;
        }

        return new OrderProblem();
    }

    private $_vehicle = null;

    public function getVehicle()
    {
        if ($this->_vehicle != null && $this->_vehicle->id != null)
            return $this->_vehicle;

        $this->_vehicle = Vehicle
            ::join(OrderProblem::TBL_MODEL, 
				OrderProblem::TBL_MODEL . '.id_vehicle', '=', Vehicle::TBL_MODEL . '.id')
            ->join(Act::TBL_MODEL, 
				Act::TBL_MODEL . '.id_ord_problem', '=', OrderProblem::TBL_MODEL . '.id')
            ->where(Act::TBL_MODEL.'.id', $this->id)
            ->select(Vehicle::TBL_MODEL.'.*')
            ->get()
            ->first();

        if ($this->_vehicle == null) {
            $this->_vehicle = new Vehicle();
        }

       return $this->_vehicle;
    }

    private $_enterprise = null;

    public function getEnterprise()
    {
        if ($this->_enterprise != null && $this->_enterprise->id != null) {
            return $this->_enterprise;
        }

        $this->_enterprise = Enterprise
            ::join(Order::TBL_MODEL, 
				Order::TBL_MODEL . '.id_enterprise', '=', Enterprise::TBL_MODEL . '.id')
            ->join(OrderProblem::TBL_MODEL, 
				OrderProblem::TBL_MODEL . '.id_order', '=', Order::TBL_MODEL . '.id')
            ->join(Act::TBL_MODEL, 
				Act::TBL_MODEL . '.id_ord_problem', '=', OrderProblem::TBL_MODEL . '.id')
            ->where(Act::TBL_MODEL . '.id', $this->id)
            ->select(Enterprise::TBL_MODEL.'.*')
            ->get()
            ->first();

        if ($this->_enterprise == null)
            $this->_enterprise = new Enterprise();

        return $this->_enterprise;
    }

    private $_order = null;

    public function getOrder()
    {
        if ($this->_order != null && $this->_order->id != null) {
            return $this->_order;
        }

        $this->_order = Order
            ::join(OrderProblem::TBL_MODEL, 
				OrderProblem::TBL_MODEL . '.id_order', '=', Order::TBL_MODEL . '.id')
            ->join(Act::TBL_MODEL, 
				Act::TBL_MODEL . '.id_ord_problem', '=', OrderProblem::TBL_MODEL . '.id')
            ->where(Act::TBL_MODEL . '.id', $this->id)
            ->select(Order::TBL_MODEL.'.*')
            ->get()
            ->first();

        if ($this->_order == null) {
            $this->_order = new Order();
        }

        return $this->_order;
    }

    //TODO процедура get_acts в mysql устарела! проверить и удалить
    public static function getAll($options = array())
    {
        $options = array_merge(array(
            'beg' => Utils::getDefaultBeginDay()->timestamp,
            'end' => Utils::getDefaultEndDay()->timestamp,
            'deleted' => null
        ), Utils::arrayFilter($options));

        $query = Act::select(Act::TBL_MODEL.'.*');

        if (Utils::isUserRole('is_master')) {
            $query
                ->join(User::TBL_ACT_MASTERS,
                    User::TBL_ACT_MASTERS . '.id_act', '=', Act::TBL_MODEL . '.id')
                ->join(User::TBL_MODEL,
                    User::TBL_MODEL . '.id', '=', User::TBL_ACT_MASTERS . '.id_user')
                ->where(User::TBL_MODEL . '.id', Auth::user()->id);
        }

        if ( !is_null($options['deleted']) ) {
            $query->where(Act::TBL_MODEL . '.deleted', $options['deleted']);
        }

        $query
            ->whereBetween(Act::TBL_MODEL . '.date_create', array($options['beg'], $options['end']))
            ->orderBy(Act::TBL_MODEL . '.date_create', 'desc');

        return $query->get();
    }

    public static function getByOrderProblem($id)
    {
        $work = Act
            ::join(OrderProblem::TBL_MODEL, 
				Act::TBL_MODEL . '.id_ord_problem', '=', OrderProblem::TBL_MODEL . '.id')
            ->where(OrderProblem::TBL_MODEL . '.id', $id)
            ->select(Act::TBL_MODEL.'.*')
            ->get()
            ->first();

        if ($work != null) return $work;

        return new Act();
    }

    public static function getByVehicle($id, $end_date = null)
    {
        $query = Act
            ::join(OrderProblem::TBL_MODEL, 
				Act::TBL_MODEL . '.id_ord_problem', '=', OrderProblem::TBL_MODEL . '.id')
            ->join(Vehicle::TBL_MODEL, 
				OrderProblem::TBL_MODEL . '.id_vehicle', '=', Vehicle::TBL_MODEL . '.id')
            ->where(Vehicle::TBL_MODEL . '.id', $id)
            ->where(Act::TBL_MODEL .'.deleted', 0);

        if ($end_date != null) {
            $query->where(Act::TBL_MODEL . '.date_create', '<',  $end_date);
        }

        return $query
            ->select(Act::TBL_MODEL.'.*')
            ->get();
    }
}
