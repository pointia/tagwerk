import ExampleAreaView from '../components/ExampleAreaView.vue'

export default defineNuxtPlugin(() => {
  registerAreaView('tagwerk/example-area', ExampleAreaView)
})
