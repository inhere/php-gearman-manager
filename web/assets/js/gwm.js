/**
 * Created by inhere on 2017/5/22.
 */

// 务必在加载 Vue 之后，立即同步设置以下内容
Vue.config.devtools = true

const routes = [{
  path: '/home',
  component: {
    template: '#page-home'
  }
}, {
  path: '/server-info',
  component: {
    template: '#page-server-info',
    // created () {
    // },
    updated () {
      console.log('vm updated')
      this.loadPlugin()
    },
    data: function () {
      return {
        statusInfo: [],
        statusFields: {
          job_name: {label: "Job name", sortable: true },
          server: {label: "Server name", sortable: true },
          in_queue: {label: "in queue"},
          in_running: {label: "in running"},
          capable_workers: {label: "capable workers"}
        },
        workersInfo: [],
        workersFields: {
          id: {label: "ID", sortable: true },
          ip: {label: "IP"},
          server: {label: "Server"},
          job_names: {label: "Job list of the worker"}
        },
        servers: [],
        serversFields: {
          index: {label: "Index", sortable: true },
          name: {label: "Name", sortable: true },
          address: {label: "Address", },
          version: {label: "Version", }
        },
        stsCurPage: 1,
        stsPerPage: 10,
        stsFilter: null,
        wkrCurPage: 1,
        wkrPerPage: 10,
        wkrFilter: null,
        tabIndex: null,
        svrFilter: null
      }
    }
  }
}, {
  path: '*',
  redirect: '/home'
}]

const router = new VueRouter({
  routes // （缩写）相当于 routes: routes
})

const vm = new Vue({
  router: router,
  components: {
    'app-header': {
      template: '#app-header'
    },
    'app-footer': {
      props: ['projInfo'],
      template: '#app-footer'
    }
  },
  data: {
    projInfo: {}
  },
  created () {
    this.fetch()
  },
  mounted () {
    // this.$nextTick(() => {
    //   this.fetch()
    // })
  },
  methods: {
    details(item) {
      alert(JSON.stringify(item));
    },
    fetch () {
      const self = this

      axios.get('/',{
        params: {
          r: 'proj-info'
        }
      })
      .then(({data, status}) => {
        console.log(data)
        self.projInfo = data.data
      }).catch(err => {
        console.error(err)
      })
    }
  }
}).$mount("#app")